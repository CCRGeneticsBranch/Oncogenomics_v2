<?php namespace LaravelAcl\Library\Form;

interface FormInterface
{
    public function process(array $input);
    public function delete(array $input);
    public function getErrors();
}
