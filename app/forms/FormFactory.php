<?php

namespace App\Forms;

use Nette;
use Nette\Application\UI\Form;

class FormFactory
{
	use Nette\SmartObject;

	/**
	 * @return Form
	 */
	public function create()
	{
		$form = new Form;
		return $form;
	}
        function makeBootstrap3(Form $form)
        {
                $renderer = $form->getRenderer();
		$renderer->wrappers['error']['container'] = null;
		$renderer->wrappers['error']['item'] = 'div class="alert alert-danger"';              
                $renderer->wrappers['controls']['container'] = NULL;
                $renderer->wrappers['pair']['container'] = 'div class=form-group';
                $renderer->wrappers['pair']['.error'] = 'has-error';
                $renderer->wrappers['control']['container'] = 'div class=col-sm-9';
                $renderer->wrappers['label']['container'] = 'div class="col-sm-3 control-label"';
                $renderer->wrappers['control']['description'] = 'span class=help-block';
                $renderer->wrappers['control']['errorcontainer'] = 'span class=help-block';                
                $form->onRender[] = function ($form) {
                        foreach ($form->getControls() as $control) {
                                $type = $control->getOption('type');
                                if ($type === 'button') {
                                        $control->getControlPrototype()->addClass(empty($usedPrimary) ? 'btn btn-primary' : 'btn btn-secondary');
                                        $usedPrimary = true;
                                } elseif (in_array($type, ['text', 'textarea', 'select'], true)) {
                                        $control->getControlPrototype()->addClass('form-control');
                                } elseif ($type === 'file') {
                                        $control->getControlPrototype()->addClass('form-control-file');
                                } elseif (in_array($type, ['checkbox', 'radio'], true)) {
                                        if ($control instanceof Nette\Forms\Controls\Checkbox) {
                                                $control->getLabelPrototype()->addClass('form-check-label');
                                        } else {
                                                $control->getItemLabelPrototype()->addClass('form-check-label');
                                        }
                                        $control->getControlPrototype()->addClass('form-check-input');
                                        $control->getSeparatorPrototype()->setName('div')->addClass('form-check');
                                }
                        }
                };
        }
        function makeBootstrap4(Form $form)
        {
                $renderer = $form->getRenderer();
		$renderer->wrappers['error']['container'] = null;
		$renderer->wrappers['error']['item'] = 'div class="alert alert-danger"';
                $renderer->wrappers['controls']['container'] = null;
                $renderer->wrappers['pair']['container'] = 'div class="form-group row"';
                $renderer->wrappers['pair']['.error'] = 'has-danger';
                $renderer->wrappers['control']['container'] = 'div class=col-sm-9';
                $renderer->wrappers['label']['container'] = 'div class="col-sm-3 col-form-label"';
                $renderer->wrappers['control']['description'] = 'span class=form-text';
                $renderer->wrappers['control']['errorcontainer'] = 'span class=form-control-feedback';
                $form->onRender[] = function ($form) {
                        foreach ($form->getControls() as $control) {
                                $type = $control->getOption('type');
                                if ($type === 'button') {
                                        $control->getControlPrototype()->addClass(empty($usedPrimary) ? 'btn btn-primary' : 'btn btn-secondary');
                                        $usedPrimary = true;
                                } elseif (in_array($type, ['text', 'textarea', 'select'], true)) {
                                        $control->getControlPrototype()->addClass('form-control');
                                } elseif ($type === 'file') {
                                        $control->getControlPrototype()->addClass('form-control-file');
                                } elseif (in_array($type, ['checkbox', 'radio'], true)) {
                                        if ($control instanceof Nette\Forms\Controls\Checkbox) {
                                                $control->getLabelPrototype()->addClass('form-check-label');
                                        } else {
                                                $control->getItemLabelPrototype()->addClass('form-check-label');
                                        }
                                        $control->getControlPrototype()->addClass('form-check-input');
                                        $control->getSeparatorPrototype()->setName('div')->addClass('form-check');
                                }
                        }
                };
        }
        function makeBootstrapCervenan(Form $form)
        {
                $renderer = $form->getRenderer();
                //$renderer->wrappers['form']['container'] = 'div class="well"';
                //$renderer->wrappers['form']['container'] = 'div class="form-group"';
		$renderer->wrappers['error']['container'] = null;
		$renderer->wrappers['error']['item'] = 'div class="alert alert-danger"';
                $renderer->wrappers['controls']['container'] = null;
                //$renderer->wrappers['pair']['container'] = 'div class="form-group row"';
                $renderer->wrappers['pair']['container'] = 'div class="form-group"';
                $renderer->wrappers['pair']['.error'] = 'has-danger';
                //$renderer->wrappers['control']['container'] = 'div class=col-sm-9';
                $renderer->wrappers['control']['container'] = '';
                $renderer->wrappers['label']['container'] = 'div class="col-form-label"';
                $renderer->wrappers['control']['description'] = 'span class=form-text';
                $renderer->wrappers['control']['errorcontainer'] = 'span class="label label-danger"';
                $form->onRender[] = function ($form) {
                        foreach ($form->getControls() as $control) {
                                $type = $control->getOption('type');
                                if ($type === 'button') {
                                        $control->getControlPrototype()->addClass(empty($usedPrimary) ? 'btn btn-primary' : 'btn btn-secondary');
                                        $usedPrimary = true;
                                } elseif (in_array($type, ['text', 'textarea', 'select'], true)) {
                                        $control->getControlPrototype()->addClass('form-control');
                                } elseif ($type === 'file') {
                                        $control->getControlPrototype()->addClass('form-control-file');
                                } elseif (in_array($type, ['checkbox', 'radio'], true)) {
                                        if ($control instanceof Nette\Forms\Controls\Checkbox) {
                                                $control->getLabelPrototype()->addClass('form-check-label');
                                        } else {
                                                $control->getItemLabelPrototype()->addClass('form-check-label');
                                        }
                                        $control->getControlPrototype()->addClass('form-check-input');
                                        $control->getSeparatorPrototype()->setName('div')->addClass('form-check');
                                }
                        }
                };
        }
        function makeBootstrap3Cervenan(Form $form)
        {
                $renderer = $form->getRenderer();
		$renderer->wrappers['error']['container'] = null;
		$renderer->wrappers['error']['item'] = 'div class="alert alert-danger"';
                $renderer->wrappers['controls']['container'] = null;
                $renderer->wrappers['pair']['container'] = 'div class="form-group"';
                $renderer->wrappers['pair']['.error'] = 'has-error';
                //$renderer->wrappers['control']['container'] = 'div class=col-sm-9';
                $renderer->wrappers['control']['container'] = '';
                $renderer->wrappers['label']['container'] = 'div class="col-form-label"';
                $renderer->wrappers['control']['description'] = 'span class=form-text';
                $renderer->wrappers['control']['errorcontainer'] = 'span class="label label-danger"';
                $form->onRender[] = function ($form) {
                        foreach ($form->getControls() as $control) {
                                $type = $control->getOption('type');
                                if ($type === 'button') {
                                        $control->getControlPrototype()->addClass(empty($usedPrimary) ? 'btn btn-primary' : 'btn btn-secondary');
                                        $usedPrimary = true;
                                } elseif (in_array($type, ['text', 'textarea', 'select'], true)) {
                                        $control->getControlPrototype()->addClass('form-control');
                                } elseif ($type === 'file') {
                                        $control->getControlPrototype()->addClass('form-control-file');
                                } elseif (in_array($type, ['checkbox', 'radio'], true)) {
                                        if ($control instanceof Nette\Forms\Controls\Checkbox) {
                                                $control->getLabelPrototype()->addClass('form-check-label');
                                        } else {
                                                $control->getItemLabelPrototype()->addClass('form-check-label');
                                        }
                                        $control->getControlPrototype()->addClass('form-check-input');
                                        $control->getSeparatorPrototype()->setName('div')->addClass('form-check');
                                }
                        }
                };
        }

}
