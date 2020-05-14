<?php
namespace EMS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="EMS\CoreBundle\Repository\AuthTokenRepository")
 * @ORM\Table(name="auth_tokens",
 *      uniqueConstraints={@ORM\UniqueConstraint(name="auth_tokens_value_unique", columns={"value"})}
 * )
 * @ORM\HasLifecycleCallbacks()
 */
class AuthToken
{
    /**
     * @ORM\Id
     * @ORM\Column(name="id", type="integer")
     * @ORM\GeneratedValue
     */
    protected $id;

    /**
     * @ORM\Column(name="value", type="string")
     */
    protected $value;

    /**
     * @ORM\Column(name="created", type="datetime")
     * @var \DateTime
     */
    protected $created;

    /**
     * @ORM\Column(name="modified", type="datetime")
     * @var \DateTime
     */
    protected $modified;

    /**
     * @ORM\ManyToOne(targetEntity="UserInterface", inversedBy="authTokens")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id")
     * @var UserInterface
     */
    protected $user;

    /**
     * Constructor: initialize the authentication key
     */
    public function __construct(UserInterface $user)
    {
        $this->value = base64_encode(random_bytes(50));
        $this->user = $user;
    }
    
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
    }

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set value
     *
     * @param string $value
     *
     * @return AuthToken
     */
    public function setValue($value)
    {
        $this->value = $value;

        return $this;
    }

    /**
     * Get value
     *
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Set created
     *
     * @param \DateTime $created
     *
     * @return AuthToken
     */
    public function setCreated($created)
    {
        $this->created = $created;

        return $this;
    }

    /**
     * Get created
     *
     * @return \DateTime
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     * Set modified
     *
     * @param \DateTime $modified
     *
     * @return AuthToken
     */
    public function setModified($modified)
    {
        $this->modified = $modified;

        return $this;
    }

    /**
     * Get modified
     *
     * @return \DateTime
     */
    public function getModified()
    {
        return $this->modified;
    }

    /**
     * Set user
     *
     * @param \EMS\CoreBundle\Entity\UserInterface $user
     *
     * @return AuthToken
     */
    public function setUser(\EMS\CoreBundle\Entity\UserInterface $user = null)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get user
     *
     * @return \EMS\CoreBundle\Entity\UserInterface
     */
    public function getUser()
    {
        return $this->user;
    }
}
