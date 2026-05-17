<?php

namespace App\Command;

use App\Entity\Course;
use App\Repository\TransactionRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

#[AsCommand(name: 'payment:report', description: 'Send monthly paid courses report.')]
final class PaymentReportCommand extends Command
{
    public function __construct(
        private readonly TransactionRepository $transactionRepository,
        private readonly MailerInterface $mailer,
        private readonly string $fromEmail,
        private readonly string $reportEmail,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $periodStart = new \DateTimeImmutable('first day of last month 00:00:00');
        $periodEnd = new \DateTimeImmutable('first day of this month 00:00:00');

        $reportRows = $this->transactionRepository->getPaidCoursesReport($periodStart, $periodEnd);
        $formattedRows = [];
        $grandTotal = 0.0;

        foreach ($reportRows as $row) {
            $amount = (float) $row['totalAmount'];
            $grandTotal += $amount;

            $formattedRows[] = [
                'title' => $row['title'],
                'type' => $this->mapCourseType((int) $row['courseType']),
                'paymentsCount' => (int) $row['paymentsCount'],
                'totalAmount' => number_format($amount, 2, '.', ''),
            ];
        }

        $textLines = [
            sprintf(
                'Отчет об оплаченных курсах за период %s - %s',
                $periodStart->format('d.m.Y'),
                $periodEnd->modify('-1 second')->format('d.m.Y')
            ),
        ];

        foreach ($formattedRows as $row) {
            $textLines[] = '';
            $textLines[] = $row['title'];
            $textLines[] = sprintf('Тип курса: %s', $row['type']);
            $textLines[] = sprintf('Число аренд/покупок: %d', $row['paymentsCount']);
            $textLines[] = sprintf('Общая сумма: %s', $row['totalAmount']);
        }

        $textLines[] = '';
        $textLines[] = sprintf('Итого: %s', number_format($grandTotal, 2, '.', ''));

        $this->mailer->send(
            (new TemplatedEmail())
                ->from(new Address($this->fromEmail, 'StudyOn Billing'))
                ->to($this->reportEmail)
                ->subject(sprintf(
                    'Отчет по оплатам за %s',
                    $periodStart->format('m.Y')
                ))
                ->text(implode("\n", $textLines))
                ->htmlTemplate('email.html.twig')
                ->context([
                    'heading' => sprintf(
                        'Отчет об оплаченных курсах за период %s - %s',
                        $periodStart->format('d.m.Y'),
                        $periodEnd->modify('-1 second')->format('d.m.Y')
                    ),
                    'reportRows' => $formattedRows,
                    'grandTotal' => number_format($grandTotal, 2, '.', ''),
                    'isReport' => true,
                ])
        );

        $output->writeln(sprintf('Sent report email to %s.', $this->reportEmail));

        return Command::SUCCESS;
    }

    private function mapCourseType(int $type): string
    {
        return match ($type) {
            Course::TYPE_RENT => 'rent',
            Course::TYPE_BUY => 'buy',
            Course::TYPE_FREE => 'free',
            default => 'unknown',
        };
    }
}
