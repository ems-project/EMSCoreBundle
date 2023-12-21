<?php

namespace EMS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use EMS\CommonBundle\Entity\CreatedModifiedTrait;
use EMS\Helpers\Standard\DateTime;

/**
 * @ORM\Table(name="auth_tokens",
 *      uniqueConstraints={@ORM\UniqueConstraint(name="auth_tokens_value_unique", columns={"value"})}
 * )
 *
 * @ORM\Entity()
 *
 * @ORM\HasLifecycleCallbacks()
 */
class AuthToken
{
    use CreatedModifiedTrait;

    /**
     * @ORM\Id
     *
     * @ORM\Column(name="id", type="integer")
     *
     * @ORM\GeneratedValue
     */
    private int $id;

    /**
     * @ORM\Column(name="value", type="string")
     */
    private string $value;

    public function __construct(/**
     * @ORM\ManyToOne(targetEntity="User", inversedBy="authTokens")
     *
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id")
     */
        private UserInterface $user)
    {
        $this->value = \base64_encode(\random_bytes(50));

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
