<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200725185057_new_cities extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('INSERT INTO zip_city (zip_code,city) VALUES ("38340","La Sure en Chartreuse"),("38260","Porte-des-Bonnevaux"),("38710","Châtel-en-Trièves") ');
    }

       public function down(Schema $schema) : void
     {
         // this down() migration is auto-generated, please modify it to your needs
         $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
     }


}
