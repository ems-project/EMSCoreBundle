<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component\MediaLibrary\Request;

use EMS\Helpers\Standard\Json;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

class MediaLibraryRequest
{
    public readonly int $from;
    public readonly string $path;

    public readonly ?string $folderId;

    public function __construct(
        private readonly Request $request
    ) {
        $this->from = $request->query->getInt('from');
        $this->path = $request->query->has('path') ? $request->query->get('path').'/' : '/';

        $this->folderId = $this->request->get('folderId');
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContentJson(): array
    {
        return Json::decode($this->request->getContent());
    }

    /**
     * @return array<mixed>
     */
    public function getFlashes(): array
    {
        return $this->getSession()->getFlashBag()->all();
    }

    public function clearFlashes(): void
    {
        $this->getSession()->getFlashBag()->clear();
    }

    private function getSession(): Session
    {
        /** @var Session $session */
        $session = $this->request->getSession();

        return $session;
    }
}
