<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ChangeExpertNoteField extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('experts');
        $table->changeColumn('note', 'text', ['null' => true])
              ->update();
    }
}
