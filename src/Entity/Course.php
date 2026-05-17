<?php

namespace App\Entity;

use App\Repository\CourseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: CourseRepository::class)]
#[ORM\Table(name: 'billing_course')]
#[ORM\UniqueConstraint(name: 'UNIQ_BILLING_COURSE_CODE', fields: ['code'])]
#[UniqueEntity(fields: ['code'], message: 'Course with this code already exists.')]
class Course
{
    public const TYPE_FREE = 0;
    public const TYPE_RENT = 1;
    public const TYPE_BUY = 2;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $code = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: 'smallint')]
    private ?int $type = null;

    #[ORM\Column(nullable: true)]
    private ?float $price = null;

    /**
     * @var Collection<int, Transaction>
     */
    #[ORM\OneToMany(mappedBy: 'course', targetEntity: Transaction::class)]
    private Collection $transactions;

    public function __construct()
    {
        $this->transactions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getType(): ?int
    {
        return $this->type;
    }

    public function setType(int $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(?float $price): static
    {
        $this->price = $price;

        return $this;
    }

    /**
     * @return Collection<int, Transaction>
     */
    public function getTransactions(): Collection
    {
        return $this->transactions;
    }

    public function addTransaction(Transaction $transaction): static
    {
        if (!$this->transactions->contains($transaction)) {
            $this->transactions->add($transaction);
            $transaction->setCourse($this);
        }

        return $this;
    }

    public function removeTransaction(Transaction $transaction): static
    {
        if ($this->transactions->removeElement($transaction) && $transaction->getCourse() === $this) {
            $transaction->setCourse(null);
        }

        return $this;
    }

    public function getTypeName(): string
    {
        return match ($this->type) {
            self::TYPE_FREE => 'free',
            self::TYPE_RENT => 'rent',
            self::TYPE_BUY => 'buy',
            default => throw new \LogicException(sprintf('Unsupported course type "%s".', (string) $this->type)),
        };
    }

    public static function typeFromName(string $typeName): int
    {
        return match ($typeName) {
            'free' => self::TYPE_FREE,
            'rent' => self::TYPE_RENT,
            'buy' => self::TYPE_BUY,
            default => throw new \LogicException(sprintf('Unsupported course type name "%s".', $typeName)),
        };
    }
}
