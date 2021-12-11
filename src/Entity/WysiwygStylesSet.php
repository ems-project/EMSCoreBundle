<?php

namespace EMS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * DataField.
 *
 * @ORM\Table(name="wysiwyg_styles_set")
 * @ORM\Entity(repositoryClass="EMS\CoreBundle\Repository\WysiwygStylesSetRepository")
 * @ORM\HasLifecycleCallbacks()
 */
class WysiwygStylesSet
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created", type="datetime")
     */
    private $created;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="modified", type="datetime")
     */
    private $modified;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=255)
     */
    private $name;

    /**
     * @var string
     *
     * @ORM\Column(name="config", type="text", nullable=true)
     */
    private $config;

    /**
     * @var int
     *
     * @ORM\Column(name="orderKey", type="integer")
     */
    private $orderKey;

    /**
     * @var string|null
     *
     * @ORM\Column(name="format_tags", type="string", length=255, nullable=true)
     */
    private $formatTags = 'p;h1;h2;h3;h4;h5;h6;pre;address;div';

    /**
     * @ORM\Column(name="table_default_css", type="string", length=255, nullable=false, options={"default" : "table table-bordered"})
     */
    private string $tableDefaultCss = 'table table-border';

    /**
     * @var string|null
     *
     * @ORM\Column(name="content_css", type="string", length=2048, nullable=true)
     */
    private $contentCss;

    /**
     * @var string|null
     *
     * @ORM\Column(name="content_js", type="string", length=2048, nullable=true)
     */
    private $contentJs;

    /**
     * @var array<string, mixed>|null
     *
     * @ORM\Column(name="assets", type="json", nullable=true)
     */
    private $assets;

    /**
     * @var string|null
     *
     * @ORM\Column(name="save_dir", type="string", length=2048, nullable=true)
     */
    private $saveDir;

    /**
     * @ORM\PrePersist
     * @ORM\PreUpdate
     */
    public function updateModified()
    {
        $this->modified = new \DateTime();
        if (!isset($this->created)) {
            $this->created = $this->modified;
        }
        if (!isset($this->orderKey)) {
            $this->orderKey = 0;
        }
    }

    /******************************************************************
     *
     * Generated functions
     *
     *******************************************************************/

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set created.
     *
     * @param \DateTime $created
     *
     * @return WysiwygStylesSet
     */
    public function setCreated($created)
    {
        $this->created = $created;

        return $this;
    }

    /**
     * Get created.
     *
     * @return \DateTime
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     * Set modified.
     *
     * @param \DateTime $modified
     *
     * @return WysiwygStylesSet
     */
    public function setModified($modified)
    {
        $this->modified = $modified;

        return $this;
    }

    /**
     * Get modified.
     *
     * @return \DateTime
     */
    public function getModified()
    {
        return $this->modified;
    }

    /**
     * Set name.
     *
     * @param string $name
     *
     * @return WysiwygStylesSet
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set config.
     *
     * @param string $config
     *
     * @return WysiwygStylesSet
     */
    public function setConfig($config)
    {
        $this->config = $config;

        return $this;
    }

    /**
     * Get config.
     *
     * @return string
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Set orderKey.
     *
     * @param int $orderKey
     *
     * @return WysiwygStylesSet
     */
    public function setOrderKey($orderKey)
    {
        $this->orderKey = $orderKey;

        return $this;
    }

    /**
     * Get orderKey.
     *
     * @return int
     */
    public function getOrderKey()
    {
        return $this->orderKey;
    }

    public function getFormatTags(): ?string
    {
        return $this->formatTags;
    }

    public function setFormatTags(?string $formatTags): WysiwygStylesSet
    {
        $this->formatTags = $formatTags;

        return $this;
    }

    public function getContentCss(): ?string
    {
        return $this->contentCss;
    }

    public function setContentCss(?string $contentCss): WysiwygStylesSet
    {
        $this->contentCss = $contentCss;

        return $this;
    }

    /**
     * @return array<string,string|int>|null
     */
    public function getAssets(): ?array
    {
        return $this->assets;
    }

    /**
     * @param array<string, mixed>|null $assets
     */
    public function setAssets(?array $assets): WysiwygStylesSet
    {
        $this->assets = $assets;

        return $this;
    }

    public function getSaveDir(): ?string
    {
        return $this->saveDir;
    }

    public function setSaveDir(?string $saveDir): WysiwygStylesSet
    {
        $this->saveDir = $saveDir;

        return $this;
    }

    public function getTableDefaultCss(): string
    {
        return $this->tableDefaultCss;
    }

    public function setTableDefaultCss(?string $tableDefaultCss): WysiwygStylesSet
    {
        $this->tableDefaultCss = $tableDefaultCss ?? '';

        return $this;
    }

    public function getContentJs(): ?string
    {
        return $this->contentJs;
    }

    public function setContentJs(?string $contentJs): void
    {
        $this->contentJs = $contentJs;
    }
}
