<?php

namespace Features\Airbnb\Decorator;


use Controller\Views\TemplateDecorator\AbstractDecorator;
use Controller\Views\TemplateDecorator\Arguments\ArgumentInterface;
use Controller\Views\TemplateDecorator\Arguments\CatDecoratorArguments;
use RuntimeException;

class CatDecorator extends AbstractDecorator {

    private CatDecoratorArguments $arguments;

    /**
     * @throws RuntimeException
     */
    public function decorate(?ArgumentInterface $arguments = null): void
    {
        if (!$arguments instanceof CatDecoratorArguments) {
            throw new RuntimeException('CatDecorator requires CatDecoratorArguments, got: ' . get_debug_type($arguments));
        }

        $this->arguments = $arguments;
        $this->assignCatDecorator();
    }

    protected function assignCatDecorator(): void
    {
        if ( !$this->arguments->isRevision() ) {
            $this->decorateForTranslate();
        }
    }

    protected function decorateForTranslate(): void
    {
        $this->template->footer_show_revise_link = false;
    }

}
