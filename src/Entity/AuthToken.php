<?php

namespace EMS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use EMS\Helpers\Standard\DateTime;

/**
 * @ORM\Entity(repositoryClass="EMS\CoreBundle\Repository\AuthTokenRepository")
 * @ORM\Table(name="auth_tokens",
 *      uniqueConstraints={@ORM\UniqueConstraint(name="auth_tokens_value_unique", columns={"value"})}
 * )
 * @ORM\HasLifecycleCallbacks()
 */
class AuthToken
{
    use CreatedModifiedTrait;
    /**
     * @ORM\Id
     * @ORM\Column(name="id", type="integer")
     * @ORM\GeneratedValue
     *
     * @var int
     */
    protected $id;

    /**
     * @ORM\Column(name="value", type="string")
     *
     * @var string
     */
    protected $value;

    /**
     * @ORM\ManyToOne(targetEntity="User", inversedBy="authTokens")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id")
     *
     * @var UserInterface
     */
    protected $user;

    /**
     * Constructor: initialize the authentication key.
     */
    public function __construct(UserInterface $user)
    {
        $this->value = \base64_encode(\random_bytes(50));
        $this->user = $user;

        $this->created = DateTime::create('now');
        $this->modified = DateTime::create('now');
    }

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
     * Set value.
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
     * Get value.
     *
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Set user.
     *
     * @return AuthToken
     */
    public function setUser(UserInterface $user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get user.
     *
     * @return \EMS\CoreBundle\Entity\UserInterface
     */
    public function getUser()
    {
        return $this->user;
    }
}
