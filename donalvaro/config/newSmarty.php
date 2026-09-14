<?php

declare(strict_types=1);

use Smarty\Smarty;

class newSmarty extends Smarty
{
    public function __construct(string $template = 'views')
    {
        parent::__construct();

        $baseDir = dirname(__FILE__). '/../';
        $tmpDir = $baseDir . '/TmpSmarty/' . $template;

        $this->setLeftDelimiter('[s/');
        $this->setRightDelimiter('/s]');

        $this->registerPlugin('modifier', 'print_r', 'print_r');

        $this->registerPlugin(
            'modifier',
            'pretty',
            static fn($var): string => print_r($var, true)
        );

        $this->setTemplateDir($baseDir . '/' . $template . '/');

        $this->setCompileDir($tmpDir . '/');
        $this->setConfigDir($tmpDir . '/configs/');
        $this->setCacheDir($tmpDir . '/cache/');

        if (_APP_DEBUG) {
            $this->force_compile = true;
            $this->compile_check = true;
            $this->caching = false;
        } else {
            $this->force_compile = false;
            $this->compile_check = true;
            $this->caching = false;
        }
    }
}