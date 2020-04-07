<?php

namespace Phel;

use Phel\Lang\Symbol;
use Phel\Stream\StringCharStream;
use PHPUnit\Framework\TestCase;

ini_set('xdebug.var_display_max_depth', '10');
ini_set('xdebug.var_display_max_children', '256');
ini_set('xdebug.var_display_max_data', '1024');

class EmitAndEvalCoreTest extends TestCase {

    public function testEmitAndEval() {
        Symbol::resetGen();
        $globalEnv = new GlobalEnvironment();
        $rt = new Runtime($globalEnv);
        $rt->addPath('phel\\', [__DIR__ . '/../../src/phel/']);
        $rt->loadNs('phel\core');

        $this->assertTrue(true);
    }
}