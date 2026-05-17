<?php

namespace App\Dto;

use JMS\Serializer\Annotation\Type;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class UpsertCourseDto
{
    #[Type('string')]
    #[Assert\NotBlank(message: 'Course type should not be blank.')]
    #[Assert\Choice(
        choices: ['free', 'rent', 'buy'],
        message: 'Course type must be one of: free, rent, buy.'
    )]
    public ?string $type = null;

    #[Type('string')]
    #[Assert\NotBlank(message: 'Course title should not be blank.')]
    #[Assert\Length(
        max: 255,
        maxMessage: 'Course title should not be longer than {{ limit }} characters.'
    )]
    public ?string $title = null;

    #[Type('string')]
    #[Assert\NotBlank(message: 'Course code should not be blank.')]
    #[Assert\Length(
        max: 255,
        maxMessage: 'Course code should not be longer than {{ limit }} characters.'
    )]
    public ?string $code = null;

    #[Type('double')]
    public ?float $price = null;

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        if ($this->type === null || $this->type === 'free') {
            return;
        }

        if ($this->price === null) {
            $context->buildViolation('Course price should not be blank for paid courses.')
                ->atPath('price')
                ->addViolation();

            return;
        }

        if ($this->price <= 0) {
            $context->buildViolation('Course price should be greater than 0.')
                ->atPath('price')
                ->addViolation();
        }
    }
}
