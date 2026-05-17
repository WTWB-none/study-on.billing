<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260517205105 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE billing_course ADD title VARCHAR(255) DEFAULT NULL');
        $this->addSql(<<<'SQL'
            UPDATE billing_course
            SET title = CASE code
                WHEN 'python-data-analysis' THEN 'Python для анализа данных'
                WHEN 'ux-writing-basics' THEN 'Основы UX-редактуры'
                WHEN 'sql-for-product-managers' THEN 'SQL для продакт-менеджеров'
                WHEN 'project-management-essentials' THEN 'Управление проектами: базовый курс'
                ELSE code
            END
            WHERE title IS NULL
        SQL);
        $this->addSql('ALTER TABLE billing_course ALTER COLUMN title SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE billing_course DROP title');
    }
}
