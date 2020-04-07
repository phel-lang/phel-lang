<?php

namespace Phel\Ast;

use Phel\NodeEnvironment;

interface Node {

    public function getEnv(): NodeEnvironment;
}