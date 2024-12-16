<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Wysiwyg;

use EMS\ClientHelperBundle\Helper\Asset\AssetHelperRuntime;
use EMS\CommonBundle\Common\EMSLink;
use EMS\CoreBundle\Entity\WysiwygStylesSet;
use EMS\CoreBundle\Service\WysiwygStylesSetService;
use EMS\Helpers\File\File;
use EMS\Helpers\Html\Headers;
use EMS\Helpers\Standard\Json;
use EMS\Helpers\Standard\Type;
use ScssPhp\ScssPhp\Compiler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class StylesetController extends AbstractController
{
    private ?Compiler $compiler = null;

    public function __construct(private readonly WysiwygStylesSetService $wysiwygStylesSetService, private readonly AssetHelperRuntime $assetHelperRuntime, private readonly string $templateNamespace,
    ) {
    }

    public function iframe(Request $request, string $name, string $language): Response
    {
        $emsLink = $request->get('emsLink');

        return $this->render("@$this->templateNamespace/wysiwyg_styles_set/iframe.html.twig", [
            'styleSet' => $this->wysiwygStylesSetService->getByName($name),
            'language' => $language,
            'field' => $request->get('field'),
            'fieldPath' => $request->get('fieldPath'),
            'emsLink' => $emsLink ? EMSLink::fromText($emsLink) : null,
        ]);
    }

    public function allPrefixedCSS(Request $request): Response
    {
        $size = $this->wysiwygStylesSetService->count();
        if ($size > 20) {
            throw new \RuntimeException('There is too much CSS specified to generate a prefixed CSS');
        }
        $etags = [];
        foreach ($this->wysiwygStylesSetService->get(0, $size, 'orderKey', 'asc', '') as $styleSet) {
            if (!$styleSet instanceof WysiwygStylesSet) {
                throw new \RuntimeException('Unexpected non WysiwygStylesSet entity');
            }
            if (!$styleSet->hasCSS()) {
                continue;
            }
            $etags[] = $styleSet->getCssEtag();
        }
        $response = $this->cssResponse(\sha1(Json::encode($etags)));
        if ($response->isNotModified($request)) {
            return $response;
        }

        $source = '';
        foreach ($this->wysiwygStylesSetService->get(0, $size, 'orderKey', 'asc', '') as $styleSet) {
            if (!$styleSet instanceof WysiwygStylesSet) {
                throw new \RuntimeException('Unexpected non WysiwygStylesSet entity');
            }
            if (!$styleSet->hasCSS()) {
                continue;
            }
            $name = $styleSet->getName();
            $css = $styleSet->giveContentCss();
            $sha1 = $styleSet->giveAssetsHash();
            $directory = $this->assetHelperRuntime->setVersion($sha1);
            $filename = \implode(DIRECTORY_SEPARATOR, [$directory, $css]);
            $cssContents = File::fromFilename($filename)->getContents();
            $source .= $this->compilePrefixedCss($name, $cssContents, Type::string($directory));
        }
        $response->setContent($source);

        return $response;
    }

    public function prefixedCSS(Request $request, string $name): Response
    {
        $styleSet = $this->wysiwygStylesSetService->getByName($name);
        if (null === $styleSet || !$styleSet->hasCSS()) {
            throw new NotFoundHttpException(\sprintf('CSS for Style Set %s not found', $name));
        }
        $response = $this->cssResponse($styleSet->getCssEtag());
        if ($response->isNotModified($request)) {
            return $response;
        }
        $css = $styleSet->giveContentCss();
        $sha1 = $styleSet->giveAssetsHash();
        $directory = $this->assetHelperRuntime->setVersion($sha1);
        $filename = \implode(DIRECTORY_SEPARATOR, [$directory, $css]);
        $cssContents = File::fromFilename($filename)->getContents();
        $response->setContent($this->compilePrefixedCss($name, $cssContents, Type::string($directory)));

        return $response;
    }

    private function compilePrefixedCss(string $name, string $css, string $directory): string
    {
        return $this->getCompiler()->compileString(".ems-styleset-$name {
            all: initial;
            padding: 10px;
            $css
        }", $directory)->getCss();
    }

    private function getCompiler(): Compiler
    {
        if (null === $this->compiler) {
            $this->compiler = new Compiler();
        }

        return $this->compiler;
    }

    private function cssResponse(string $etag): Response
    {
        $response = new Response();
        $response->setCache([
            'etag' => $etag,
            'max_age' => 3600,
            'public' => true,
            'private' => false,
        ]);
        $response->headers->set(Headers::CONTENT_TYPE, 'text/css');

        return $response;
    }
}
