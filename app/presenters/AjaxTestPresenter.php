<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette;

final class AjaxTestPresenter extends BasePresenter
{
    /** @var string */
    private $anyVariable;

    public function handleChangeVariable()
    {
        $this->anyVariable = 'changed value via ajax';
        if ($this->isAjax()) {
            $this->anyVariable = 'really ajax '.time();
            $this->redrawControl('ajaxChange');
        }
    }

    public function renderDefault()
    {
        if ($this->anyVariable === NULL) {
            $this->anyVariable = 'default value';
        }
        $this->template->anyVariable = $this->anyVariable;
    }  
           
}
