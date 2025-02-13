<?php

declare(strict_types=1);

namespace App\WebModule\Components;

use App\Model\CMS\Content\HtmlContentDTO;
use Nette\Application\UI\Control;

/**
 * Komponenta s HTML.
 *
 * @author Michal Májský
 * @author Jan Staněk <jan.stanek@skaut.cz>
 */
class HtmlContentControl extends Control
{
    public function render(HtmlContentDTO $content) : void
    {
        $template = $this->template;
        $template->setFile(__DIR__ . '/templates/html_content.latte');

        $template->heading = $content->getHeading();
        $template->html    = $content->getText();

        $template->render();
    }
}
