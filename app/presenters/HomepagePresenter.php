<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette;

final class HomepagePresenter extends BasePresenter
{
    public function renderDefault()
    {
//        $this->template->posts = $this->database
//            ->table('user')
//            ->order('id ASC')
//            ->limit(5);
    }

    public function renderContact()
    {
        /*
        $this->flashMessage('Testovací flash message - červeně.','w3-red');
        $this->flashMessage('Testovací flash message - modře.','w3-blue');
        */
    }    
           
}
