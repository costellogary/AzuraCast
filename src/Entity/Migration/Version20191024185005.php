<?php

declare(strict_types=1);

namespace App\Entity\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20191024185005 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE station_media ADD art_updated_at INT NOT NULL');
    }

    public function postUp(Schema $schema): void
    {
        $stations = $this->connection->fetchAll('SELECT s.* FROM station AS s');

        foreach ($stations as $station) {
            $this->write('Migrating album art for station "' . $station['name'] . '"...');

            $baseDir = $station['radio_base_dir'];
            $artDir = $baseDir . '/album_art';

            $getMediaQuery = $this->connection->executeQuery(
                'SELECT unique_id FROM station_media WHERE station_id = ?',
                [$station['id']],
                [ParameterType::INTEGER]
            );

            $mediaRowsTotal = 0;
            $mediaRowsToUpdate = [];

            while ($row = $getMediaQuery->fetch()) {
                $mediaRowsTotal++;
                $artPath = $artDir . '/' . $row['unique_id'] . '.jpg';

                if (file_exists($artPath)) {
                    $mediaRowsToUpdate[] = $row['unique_id'];
                }
            }

            $this->write('Album art exists for ' . count($mediaRowsToUpdate) . ' of ' . $mediaRowsTotal . ' media.');

            $this->connection->executeUpdate(
                'UPDATE station_media SET art_updated_at=UNIX_TIMESTAMP() WHERE unique_id IN (?)',
                [$mediaRowsToUpdate],
                [Connection::PARAM_STR_ARRAY]
            );
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE station_media DROP art_updated_at');
    }
}
