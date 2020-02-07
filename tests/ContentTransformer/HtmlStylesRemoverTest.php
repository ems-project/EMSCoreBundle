<?php

namespace EMS\CoreBundle\Tests\ContentTransformer;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use EMS\CoreBundle\ContentTransformer\HtmlStylesRemover;

class HtmlStylesRemoverTest extends WebTestCase
{
    public function testRemoveSimpleInline()
    {
        $input = <<<HTML
<p>Lorem ipsum dolor <span class="removable-style-newWord">sit amet</span>, consectetur adipiscing elit.</p>
HTML;
        $output = <<<HTML
<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>
HTML;

        $htmlStylesRemover = new HtmlStylesRemover($input);
        $inputChanged = $htmlStylesRemover->removeHtmlStyles();

        $this->assertEquals($output, $inputChanged);
    }

    public function testRemoveSimpleBlock()
    {
        $input = <<<HTML
<div class="removable-style-deletedContent"><p>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p></div>
HTML;
        $output = <<<HTML
<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>
HTML;

        $htmlStylesRemover = new HtmlStylesRemover($input);
        $inputChanged = $htmlStylesRemover->removeHtmlStyles();

        $this->assertEquals($output, $inputChanged);
    }

    public function testInlineNestedInAlertBlock()
    {
        $input = <<<HTML
<div class="message alert">Lorem ipsum dolor sit amet, <span class="removable-style-deletedWord">consectetur </span>adipiscing elit.</div>
HTML;
        $output = <<<HTML
<div class="message alert">Lorem ipsum dolor sit amet, consectetur adipiscing elit.</div>
HTML;

        $htmlStylesRemover = new HtmlStylesRemover($input);
        $inputChanged = $htmlStylesRemover->removeHtmlStyles();

        $this->assertEquals($output, $inputChanged);
    }

    public function testBlockNestedInReadmoreBlock()
    {
        $input = <<<HTML
<div class="readMoreContent">
    <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p> <!-- parent -->
    <div class="removable-style-newContent"><p>In quis eleifend nisi. Vestibulum porttitor.</p></div> <!-- first child -->
    <p>Curabitur non eleifend felis.</p> <!-- second child -->
</div>
HTML;
        $output = <<<HTML
<div class="readMoreContent">
    <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p> <!-- parent -->
    <p>In quis eleifend nisi. Vestibulum porttitor.</p> <!-- first child -->
    <p>Curabitur non eleifend felis.</p> <!-- second child -->
</div>
HTML;

        $htmlStylesRemover = new HtmlStylesRemover($input);
        $inputChanged = $htmlStylesRemover->removeHtmlStyles();

        $this->assertEquals($output, $inputChanged);
    }

    public function testSpanToRemoveFollowedByEmOrStrong()
    {
        $input = <<<HTML
<div class="question">Lorem <span class="removable-style-deletedWord">ipsum dolor sit amet</span>, <em>consectetur </em>adipiscing elit.</div>
<div class="response">In quis <strong>eleifend </strong>nisi. <span class="removable-style-deletedWord">Vestibulum </span>porttitor.</div>
HTML;
        $output = <<<HTML
<div class="question">Lorem ipsum dolor sit amet, <em>consectetur </em>adipiscing elit.</div>
<div class="response">In quis <strong>eleifend </strong>nisi. Vestibulum porttitor.</div>
HTML;

        $htmlStylesRemover = new HtmlStylesRemover($input);
        $inputChanged = $htmlStylesRemover->removeHtmlStyles();

        $this->assertEquals($output, $inputChanged);
    }

    public function testSpanToRemoveWithStrongInsideIt()
    {
        $input = <<<HTML
<p>In quis <span class="removable-style-newWord"><strong>eleifend </strong></span>nisi. Vestibulum porttitor.</p>
HTML;
        $output = <<<HTML
<p>In quis <strong>eleifend </strong>nisi. Vestibulum porttitor.</p>
HTML;

        $htmlStylesRemover = new HtmlStylesRemover($input);
        $inputChanged = $htmlStylesRemover->removeHtmlStyles();

        $this->assertEquals($output, $inputChanged);
    }

    public function testDivToRemoveWithTagsInsideIt()
    {
        $input = <<<HTML
<div class="removable-style-newContent"><p>In sed dolor quis nulla <strong>accumsan </strong>ornare; In id <u>libero</u> sed <em>sapien semper</em> tristique sit amet eu mauris.</p></div>
HTML;
        $output = <<<HTML
<p>In sed dolor quis nulla <strong>accumsan </strong>ornare; In id <u>libero</u> sed <em>sapien semper</em> tristique sit amet eu mauris.</p>
HTML;

        $htmlStylesRemover = new HtmlStylesRemover($input);
        $inputChanged = $htmlStylesRemover->removeHtmlStyles();

        $this->assertEquals($output, $inputChanged);
    }

    public function testDivToRemoveWithSpanToRemoveInsideItWithOtherTags()
    {
        $input = <<<HTML
<div class="removable-style-deletedContent"><p>In sed dolor quis nulla <strong>accumsan </strong>ornare; In id <u>libero</u> sed <em>sapien semper</em> tristique sit amet eu <span class="removable-style-deletedWord">mauris</span>.</p></div>
HTML;
        $output = <<<HTML
<p>In sed dolor quis nulla <strong>accumsan </strong>ornare; In id <u>libero</u> sed <em>sapien semper</em> tristique sit amet eu mauris.</p>
HTML;

        $htmlStylesRemover = new HtmlStylesRemover($input);
        $inputChanged = $htmlStylesRemover->removeHtmlStyles();

        $this->assertEquals($output, $inputChanged);
    }

    public function testDivtoRemoveWithSpanToRemoveInsideItWithOtherTagsBis()
    {
        $input = <<<HTML
<p>Lo<strong>rem ipsum <span class="removable-style-newWord">dolor </span>sit amet, <em>consectetur </em>ad</strong>ipiscing elit.</p> 
HTML;
        $output = <<<HTML
<p>Lo<strong>rem ipsum dolor sit amet, <em>consectetur </em>ad</strong>ipiscing elit.</p>
HTML;

        $htmlStylesRemover = new HtmlStylesRemover($input);
        $inputChanged = $htmlStylesRemover->removeHtmlStyles();

        $this->assertEquals($output, $inputChanged);
    }

    public function testSpanToRemoveWithAnotherSpanInsideIt()
    {
        $input = <<<HTML
<p><span class="removable-style-deletedWord">Nam <span class="hidden">lobortis </span>dolor ege</span>t felis</p>
HTML;
        $output = <<<HTML
<p>Nam <span class="hidden">lobortis </span>dolor eget felis</p>
HTML;

        $htmlStylesRemover = new HtmlStylesRemover($input);
        $inputChanged = $htmlStylesRemover->removeHtmlStyles();

        $this->assertEquals($output, $inputChanged);
    }
}
