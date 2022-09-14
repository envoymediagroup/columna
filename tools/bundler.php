<?php
ini_set('xdebug.max_nesting_level', 3000);
chdir(__DIR__);
require ('../vendor/autoload.php');

//Self-invoking

use PhpParser\Node;
use PhpParser\Comment;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Node\Name;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter;

class Bundler {

    protected const SOURCE_DIRECTORY = '../src/';
    protected const BUNDLED_CLASS_PREFIX = 'Bundled';

    /**
     * @throws Exception
     * @return void
     */
    public function bundle(string $primary_class) {
        $used_classes = $this->findUsedClasses($primary_class);
        $this->bundleClasses($primary_class,$used_classes);
    }

    /**
     * @param string $class
     * @throws Exception
     * @return string[]
     */
    protected function findUsedClasses(string $class): array {
        $Parser = (new ParserFactory)->create(ParserFactory::ONLY_PHP7);
        $Tree = $Parser->parse(file_get_contents(self::classToFilePath($class)));
        $Visitor = new FindClassNamesNodeVisitor(); //Recursive
        $Traverser = new NodeTraverser;
        $Traverser->addVisitor($Visitor);
        $Traverser->traverse($Tree);
        $names = $Visitor->getClassNames();
        if (in_array($class,$names)) {
            unset($names[array_search($class,$names)]);
        }
        sort($names);
        return $names;
    }

    /**
     * @param string $primary_class
     * @param array $used_classes
     * @throws Exception
     * @return void
     */
    protected function bundleClasses(string $primary_class, array $used_classes): void {
        $all_classes = array_merge([$primary_class],$used_classes);
        $use_statements = $this->generateUseStatements($all_classes);
        $class_bodies = $this->generateClassBodies($all_classes);
        $destination_file = self::classToFilePath(self::getBundledName($primary_class));
        $contents = $this->generateBundledFileContents($all_classes,$use_statements,$class_bodies);
        if (file_exists($destination_file) && file_get_contents($destination_file) === $contents) {
            print "No changes to $destination_file\n";
        } else {
            file_put_contents($destination_file,$contents);
            print "Saved to $destination_file\n";
        }
    }

    /**
     * @param array $classes
     * @throws Exception
     * @return string[]
     */
    protected function generateUseStatements(array $classes): array {
        $use_statements = [];
        foreach ($classes as $class) {
            $use_statements = array_merge($use_statements,$this->extractUseStatementsFromClass($class));
        }

        foreach ($use_statements as &$use_stmt) {
            $use_stmt = str_replace('use \\','use ',$use_stmt);
        }
        $use_statements = array_unique($use_statements);
        sort($use_statements);

        return $use_statements;
    }

    /**
     * @param string $class
     * @throws Exception
     * @return array
     */
    protected function extractUseStatementsFromClass(string $class): array {
        $Parser = (new ParserFactory)->create(ParserFactory::ONLY_PHP7);
        $Statements = $Parser->parse(file_get_contents(self::classToFilePath($class)));
        $Nodes = (new NodeFinder)->findInstanceOf($Statements,Node\Stmt\Use_::class);
        if (empty($Nodes)) {
            return [];
        }
        $Printer = new PrettyPrinter\Standard;
        $use_statements = [];
        foreach ($Nodes as $Node) {
            $use_statements[] = $Printer->prettyPrint([$Node]);
        }
        return $use_statements;
    }

    /**
     * @param array $classes
     * @throws Exception
     * @return array
     */
    protected function generateClassBodies(array $classes): array {
        $class_bodies = [];
        $Printer = new PrettyPrinter\Standard();

        foreach ($classes as $class) {
            $Class_Statement = $this->getClassStatement($class);
            $Class_Statement = $this->modifyClassStatementWithNewClassNames($Class_Statement,$classes);
            $class_body = $Printer->prettyPrint([$Class_Statement]);
            $class_body = str_replace("    }\n    /**","    }\n\n    /**",$class_body);
            $class_bodies[] = $class_body;
        }

        return $class_bodies;
    }

    /**
     * @param string $class
     * @throws Exception
     * @return Node\Stmt\Class_
     */
    protected function getClassStatement(string $class): Node\Stmt\Class_ {
        $Parser = (new ParserFactory)->create(ParserFactory::ONLY_PHP7);
        $class_file = self::classToFilePath($class);
        $Statements = $Parser->parse(file_get_contents($class_file));
        $Nodes = (new NodeFinder)->findInstanceOf($Statements,Node\Stmt\Class_::class);
        if (count($Nodes) === 0) {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." could not find class statement in file '{$class_file}'.");
        }
        if (count($Nodes) > 1) {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." found multiple class statements in file '{$class_file}' " .
                "before bundling.");
        }
        /** @var Node\Stmt\Class_ $Node */
        $Node = current($Nodes);
        return $Node;
    }

    /**
     * @param Node\Stmt\Class_ $Class_Statement
     * @param array $classes
     * @return Node\Stmt\Class_
     */
    protected function modifyClassStatementWithNewClassNames(
        Node\Stmt\Class_ $Class_Statement,
        array $classes
    ): Node\Stmt\Class_ {
        $Traverser = new NodeTraverser;
        $Traverser->addVisitor(new RenameClassNamesNodeVisitor());
        $Traverser->addVisitor(new ModifyDocCommentsNodeVisitor($classes));
        /** @var Node\Stmt\Class_ $Class_Statement */
        $Class_Statement = current($Traverser->traverse([$Class_Statement]));
        $Class_Statement->name = new PhpParser\Node\Name(self::getBundledName($Class_Statement->name->toString()));
        return $Class_Statement;
    }

    /**
     * @param string[] $classes
     * @param string[] $use_statements
     * @param string[] $class_bodies
     * @return string
     */
    protected function generateBundledFileContents(array $classes, array $use_statements, array $class_bodies): string {
        $classes_string = join("\n",array_map(function($str) { return ' *   '.$str; },$classes));
        $use_statements_string = join("\n",$use_statements);
        $class_bodies_string = join("\n\n",$class_bodies);
        return <<<END
<?php

namespace EnvoyMediaGroup\Columna;

$use_statements_string

/**
 * Bundles the following classes: 
$classes_string
 */
$class_bodies_string

END;
    }

    /**
     * @param string $class
     * @return string
     */
    public static function classToFilePath(string $class): string {
        return self::SOURCE_DIRECTORY . $class . '.php';
    }

    /**
     * @param string $class
     * @return bool
     */
    public static function isProjectClass(string $class): bool {
        return file_exists(self::classToFilePath($class));
    }

    /**
     * @param string $class
     * @return string
     */
    public static function getBundledName(string $class): string {
        return self::BUNDLED_CLASS_PREFIX . $class;
    }

}

class FindClassNamesNodeVisitor extends NodeVisitorAbstract {

    /** @var string[] */
    protected $class_names = [];

    /**
     * @param Node $node
     * @return null
     */
    public function leaveNode(Node $node) {
        foreach (get_object_vars($node) as $property) {
            if (
                is_a($property,Name::class) &&
                !$property->isSpecialClassName() &&
                Bundler::isProjectClass($property->toString()) &&
                !in_array($property->toString(),$this->class_names)
            ) {
                $class = $property->toString();
                $this->class_names[] = $class;

                //Recurse
                $Parser = (new ParserFactory)->create(ParserFactory::ONLY_PHP7);
                $Tree = $Parser->parse(file_get_contents(Bundler::classToFilePath($class)));
                $Traverser = new NodeTraverser;
                $Traverser->addVisitor($this);
                $Traverser->traverse($Tree);
            }
        }
        return null;
    }

    /**
     * @return string[]
     */
    public function getClassNames(): array {
        return $this->class_names;
    }
    
}

class RenameClassNamesNodeVisitor extends NodeVisitorAbstract {

    /**
     * @param Node $node
     * @return Node|null
     */
    public function leaveNode(Node $node): ?Node {
        if (
            is_a($node,Name::class) &&
            Bundler::isProjectClass($node->toString())
        ) {
            return new PhpParser\Node\Name(Bundler::getBundledName($node->toString()));
        }
        return null;
    }

}

class ModifyDocCommentsNodeVisitor extends NodeVisitorAbstract {

    /** @var string[] */
    protected $classes;

    /**
     * @param array $classes
     */
    public function __construct(array $classes) {
        $this->classes = $classes;
    }

    /**
     * @param Node $node
     * @return Node|null
     */
    public function leaveNode(Node $node): ?Node {
        if (!is_null($node->getDocComment())) {
            $text = $node->getDocComment()->getText();
            $new_text = $text;
            foreach ($this->classes as $class) {
                if (strpos($new_text,$class) !== false) {
                    $new_text = preg_replace("/\b{$class}\b/",Bundler::getBundledName($class),$new_text);
                }
            }
            if ($text !== $new_text) {
                $node->setDocComment(new Comment\Doc($new_text));
                return $node;
            }
        }
        return null;
    }

}

(new Bundler)->bundle('Reader');