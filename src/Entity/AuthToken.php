<?php

namespace EMS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use EMS\Helpers\Standard\DateTime;

/**
 * @ORM\Table(name="auth_tokens",
 *      uniqueConstraints={@ORM\UniqueConstraint(name="auth_tokens_value_unique", columns={"value"})}
 * )
 * @ORM\Entity()
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
    private $id;

    /**
     * @ORM\Column(name="value", type="string")
     */
    private string $value;

    /**
     * @ORM\ManyToOne(targetEntity="User", inversedBy="authTokens")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id")
     */
    private UserInterface $user;

    public function __construct(UserInterface $user)
    {
        $this->value = \base64_encode(\random_bytes(50));
        $this->user = $user;

        $this->created = DateTime::create('now');
        $this->modified = DateTime::create('now');
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    public function setValue(string $value): self
    {
        $this->value = $value;

        return $this;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setUser(UserInterface $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getUser(): UserInterface
    {
        return $this->user;
    }
}
