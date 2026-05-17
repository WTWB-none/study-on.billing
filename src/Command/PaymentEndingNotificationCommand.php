<?php

namespace App\Command;

use App\Entity\Transaction;
use App\Repository\TransactionRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

#[AsCommand(name: 'payment:ending:notification', description: 'Send rent ending notifications to users.')]
final class PaymentEndingNotificationCommand extends Command
{
    public function __construct(
        private readonly TransactionRepository $transactionRepository,
        private readonly MailerInterface $mailer,
        private readonly string $fromEmail,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $from = new \DateTimeImmutable('tomorrow 00:00:00');
        $to = $from->modify('+1 day');

        $transactions = $this->transactionRepository->findRentTransactionsExpiringBetween($from, $to);
        if ($transactions === []) {
            $output->writeln('No ending rent payments found.');

            return Command::SUCCESS;
        }

        $groupedTransactions = [];

        foreach ($transactions as $transaction) {
            if (!$transaction instanceof Transaction) {
                continue;
            }

            $user = $transaction->getUser();
            $course = $transaction->getCourse();
            $expiresAt = $transaction->getExpiresAt();

            if ($user === null || $course === null || $expiresAt === null || $user->getEmail() === null) {
                continue;
            }

            $groupedTransactions[$user->getEmail()][] = [
                'title' => $course->getTitle() ?? $course->getCode() ?? 'Unknown course',
                'expiresAt' => $expiresAt,
            ];
        }

        foreach ($groupedTransactions as $email => $courses) {
            $lines = array_map(
                static fn (array $course): string => sprintf(
                    '%s действует до %s.',
                    $course['title'],
                    $course['expiresAt']->format('d.m.Y H:i')
                ),
                $courses
            );

            $message = "Уважаемый клиент! У вас есть курсы, срок аренды которых подходит к концу:\n".implode("\n", $lines);

            $this->mailer->send(
                (new TemplatedEmail())
                    ->from(new Address($this->fromEmail, 'StudyOn Billing'))
                    ->to($email)
                    ->subject('Срок аренды курсов подходит к концу')
                    ->text($message)
                    ->htmlTemplate('email.html.twig')
                    ->context([
                        'heading' => 'Срок аренды курсов подходит к концу',
                        'message' => nl2br($message),
                    ])
            );
        }

        $output->writeln(sprintf('Sent %d notification email(s).', count($groupedTransactions)));

        return Command::SUCCESS;
    }
}
