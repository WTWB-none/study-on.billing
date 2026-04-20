<?php

namespace App\Dto;

use JMS\Serializer\Annotation\Type;
use Symfony\Component\Validator\Constraints as Assert;

final class RegisterUserDto
{
    #[Type('string')]
    #[Assert\NotBlank(message: 'Email should not be blank.')]
    #[Assert\Email(message: 'Invalid email address.')]
    public string $email;

    #[Type('string')]
    #[Assert\NotBlank(message: 'Password should not be blank.')]
    #[Assert\Length(min: 6, minMessage: 'Password should be at least {{ limit }} characters long.')]
    public string $password;
}
