<?php

namespace EnvoyMediaGroup\Columna;

use Exception;
use SplFileObject;

class FileHelper {

    /** @var string */
    protected $tmp_directory;

    /**
     * @param string|null $tmp_directory
     * @throws Exception
     */
    public function __construct(?string $tmp_directory = null) {
        $this->setTmpDirectory($tmp_directory ?? sys_get_temp_dir());
    }

    /**
     * @param string $tmp_directory
     * @throws Exception
     */
    protected function setTmpDirectory(string $tmp_directory): void {
        if (!is_writable($tmp_directory)) {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." directory '{$tmp_directory}' does not exist or is not writable.");
        }
        $this->tmp_directory = $tmp_directory;
    }

    /**
     * @return string
     */
    protected function generateTmpFilePath(): string {
        $rand = md5(mt_rand());
        return $this->tmp_directory . "/{$rand}.tmp";
    }

    /**
     * @param string $file_path
     * @throws Exception
     * @return SplFileObject
     */
    public function openFileForReadWithoutLock(string $file_path): SplFileObject {
        return new SplFileObject($file_path,'r');
    }

    /**
     * @throws Exception
     * @return SplFileObject
     */
    public function openNewTmpFileForReadAndWrite(): SplFileObject {
        $tmp_file_path = $this->generateTmpFilePath();
        if (!touch($tmp_file_path)) {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." could not touch tmp file '{$tmp_file_path}'.");
        }
        $TmpFile = new SplFileObject($tmp_file_path,'w+');
        //No need to lock.
        print __METHOD__ . ": $tmp_file_path\n";
        return $TmpFile;
    }

    /**
     * @param string $file_path
     * @param bool $acquire_exclusive_lock
     * @throws Exception
     * @return SplFileObject
     */
    public function openFileForWrite(string $file_path, bool $acquire_exclusive_lock): SplFileObject {
        if (!touch($file_path)) {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." could not touch file '{$file_path}'.");
        }

        if ($acquire_exclusive_lock === false) {
            return new SplFileObject($file_path,'w');
        }

        $File = new SplFileObject($file_path,'c');
        $File->flock(LOCK_EX);

        clearstatcache(true,$file_path);
        if (!file_exists($file_path)) {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." file '{$file_path}' was removed while " .
                "awaiting lock.");
        }

        $File->ftruncate(0);
        return $File;
    }

    /**
     * @param string $origin_file_path
     * @param string $destination_file_path
     * @param bool $acquire_exclusive_lock_on_destination
     * @throws Exception
     * @return void
     */
    public function moveFile(string $origin_file_path, string $destination_file_path, bool $acquire_exclusive_lock_on_destination): void {
        $OriginFile = null;
        $DestinationFile = null;
        try {
            $OriginFile = $this->openFileForReadWithoutLock($origin_file_path);
            $DestinationFile = $this->openFileForWrite($destination_file_path,$acquire_exclusive_lock_on_destination);
            while (!$OriginFile->eof()) {
                $DestinationFile->fwrite($OriginFile->fgets());
            }
        } finally {
            $this->closeAndDeleteFile($OriginFile);
            $this->closeFileAndUnlockIfNeeded($DestinationFile,$acquire_exclusive_lock_on_destination);
        }
    }

    /**
     * @param SplFileObject|null $File
     * @return void
     */
    public function closeAndDeleteFile(?SplFileObject &$File): void {
        print __METHOD__." invoked: " . (is_null($File) ? 'null' : $File->getPathname()) . "\n";
        if (is_null($File)) {
            return;
        }

        $file_path = $File->getPathname();
        $File = null;

        @unlink($file_path);
        print __METHOD__ . " unlinked: $file_path\n";
    }

    /**
     * @param SplFileObject[]|null $Files
     * @return void
     */
    public function closeFiles(?array &$Files): void {
        if (is_null($Files)) {
            return;
        }
        foreach ($Files as &$File) {
            $File = null;
        }
    }

    /**
     * @param SplFileObject[]|null $Files
     * @return void
     */
    public function closeAndDeleteFiles(?array &$Files): void {
        if (is_null($Files)) {
            return;
        }
        foreach ($Files as &$File) {
            $this->closeAndDeleteFile($File);
        }
    }

    /**
     * @param SplFileObject|null $File
     * @param bool $file_is_locked
     * @return void
     */
    public function closeFileAndUnlockIfNeeded(?SplFileObject &$File, bool $file_is_locked): void {
        if (is_null($File)) {
            return;
        }

        if ($file_is_locked) {
            $File->flock(LOCK_UN);
        }
        $File = null;
    }

}