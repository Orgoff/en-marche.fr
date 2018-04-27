<?php

namespace Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

class Version20180502142950 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE transaction (id INT UNSIGNED AUTO_INCREMENT NOT NULL, donation_id INT UNSIGNED DEFAULT NULL, paybox_result_code VARCHAR(100) DEFAULT NULL, paybox_authorization_code VARCHAR(100) DEFAULT NULL, paybox_payload JSON DEFAULT NULL COMMENT \'(DC2Type:json_array)\', paybox_date_time DATETIME NOT NULL, paybox_transaction_id VARCHAR(255) NOT NULL, paybox_subscription_id VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_723705D15A4036C7 (paybox_transaction_id), INDEX IDX_723705D14DC1279C (donation_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D14DC1279C FOREIGN KEY (donation_id) REFERENCES donations (id)');
        $this->addSql('ALTER TABLE donations ADD status VARCHAR(25) DEFAULT NULL, ADD updated_at DATETIME DEFAULT NULL, ADD paybox_order_ref VARCHAR(255) DEFAULT NULL');
        $this->addSql('UPDATE donations SET donations.status = \'finished\' WHERE donations.finished = 1');
        $this->addSql('UPDATE donations SET donations.status = \'waiting_confirmation\' WHERE donations.finished = 0');
        $this->addSql('UPDATE donations SET donations.status = \'subscription_in_progress\' WHERE donations.duration != 0');
        $this->addSql('UPDATE donations SET donations.updated_at = donations.created_at');
        $this->addSql('ALTER TABLE donations MODIFY status VARCHAR(25) NOT NULL, MODIFY updated_at DATETIME NOT NULL');
        $this->addSql(
            'INSERT INTO transaction (donation_id, paybox_result_code, paybox_authorization_code, paybox_payload, paybox_date_time, paybox_transaction_id, created_at)
                    (SELECT id, paybox_result_code, paybox_authorization_code, paybox_payload, STR_TO_DATE(
                        CONCAT(
                          SUBSTR(paybox_payload->"$.date", 2, 2),
                          \',\',
                          SUBSTR(paybox_payload->"$.date", 4, 2),
                          \',\',
                          SUBSTR(paybox_payload->"$.date", 6, 4),
                          \' \',
                          SUBSTR(paybox_payload->"$.time", 2, 8)
                        ),
                        \'%d,%m,%Y %H:%i:%s\'
                      ), paybox_payload->"$.transaction", created_at FROM donations)'
        );
        $this->addSql('ALTER TABLE donations DROP donated_at, DROP paybox_result_code, DROP paybox_authorization_code, DROP paybox_payload, DROP finished');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE transaction');
        $this->addSql('ALTER TABLE donations ADD paybox_date_time DATETIME DEFAULT NULL, ADD paybox_result_code VARCHAR(100) DEFAULT NULL COLLATE utf8_unicode_ci, ADD paybox_authorization_code VARCHAR(100) DEFAULT NULL COLLATE utf8_unicode_ci, ADD paybox_payload JSON DEFAULT NULL COLLATE utf8_unicode_ci COMMENT \'(DC2Type:json_array)\', ADD finished TINYINT(1) NOT NULL, DROP status, DROP updated_at, DROP paybox_order_ref, DROP donated_at');
    }
}
