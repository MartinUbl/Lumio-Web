<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class PopulatePublicTags extends AbstractMigration
{
    public function up(): void
    {
        if (!$this->hasTable('tags')) {
            return;
        }

        $tags = $this->tags();
        if ($this->tableIsEmpty('tags')) {
            $this->table('tags')->insert($tags)->saveData();
            return;
        }

        $statement = $this->getAdapter()->getConnection()->prepare(
            "INSERT INTO tags (id, tag_name, slug, tag_type, scope, is_active)
            VALUES (:id, :tag_name, :slug, :tag_type, :scope, :is_active)
            ON DUPLICATE KEY UPDATE
                tag_name = VALUES(tag_name),
                slug = VALUES(slug),
                tag_type = VALUES(tag_type),
                scope = VALUES(scope),
                is_active = VALUES(is_active)"
        );

        foreach ($tags as $tag) {
            $statement->execute($tag);
        }
    }

    public function down(): void
    {
        if (!$this->hasTable('tags')) {
            return;
        }

        $ids = implode(', ', array_map(
            static fn(array $tag): string => (string) $tag['id'],
            $this->tags()
        ));

        $this->execute("DELETE FROM tags WHERE id IN ($ids)");
    }

    /**
     * @return array<int, array{id: int, tag_name: string, slug: string, tag_type: string, scope: string, is_active: int}>
     */
    private function tags(): array
    {
        return [
            ['id' => 1, 'tag_name' => 'Umění', 'slug' => 'umeni', 'tag_type' => 'area', 'scope' => 'both', 'is_active' => 1],
            ['id' => 2, 'tag_name' => 'Strojírenství', 'slug' => 'strojirenstvi', 'tag_type' => 'area', 'scope' => 'both', 'is_active' => 1],
            ['id' => 3, 'tag_name' => 'AI a strojové učení', 'slug' => 'ai-a-strojove-uceni', 'tag_type' => 'area', 'scope' => 'both', 'is_active' => 1],
            ['id' => 4, 'tag_name' => 'Duševní zdraví', 'slug' => 'dusevni-zdravi', 'tag_type' => 'area', 'scope' => 'both', 'is_active' => 1],
            ['id' => 5, 'tag_name' => 'Programování', 'slug' => 'programovani', 'tag_type' => 'area', 'scope' => 'both', 'is_active' => 1],
            ['id' => 6, 'tag_name' => 'Kyberbezpečnost', 'slug' => 'kyberbezpecnost', 'tag_type' => 'area', 'scope' => 'both', 'is_active' => 1],
            ['id' => 7, 'tag_name' => 'Marketing', 'slug' => 'marketing', 'tag_type' => 'area', 'scope' => 'both', 'is_active' => 1],
            ['id' => 8, 'tag_name' => 'Management', 'slug' => 'management', 'tag_type' => 'area', 'scope' => 'both', 'is_active' => 1],
            ['id' => 9, 'tag_name' => 'Právo', 'slug' => 'pravo', 'tag_type' => 'area', 'scope' => 'both', 'is_active' => 1],
            ['id' => 10, 'tag_name' => 'Ekonomie a finance', 'slug' => 'ekonomie-a-finance', 'tag_type' => 'area', 'scope' => 'both', 'is_active' => 1],
            ['id' => 11, 'tag_name' => 'Design', 'slug' => 'design', 'tag_type' => 'area', 'scope' => 'both', 'is_active' => 1],
            ['id' => 12, 'tag_name' => 'Robotika', 'slug' => 'robotika', 'tag_type' => 'area', 'scope' => 'both', 'is_active' => 1],
            ['id' => 13, 'tag_name' => 'Zahraniční stáže', 'slug' => 'zahranicni-staze', 'tag_type' => 'area', 'scope' => 'both', 'is_active' => 1],
            ['id' => 14, 'tag_name' => 'Soft skills', 'slug' => 'soft-skills', 'tag_type' => 'area', 'scope' => 'both', 'is_active' => 1],
            ['id' => 15, 'tag_name' => 'Dějiny a historie', 'slug' => 'dejiny-a-historie', 'tag_type' => 'area', 'scope' => 'both', 'is_active' => 1],
            ['id' => 16, 'tag_name' => 'Sport a zdraví', 'slug' => 'sport-a-zdravi', 'tag_type' => 'area', 'scope' => 'both', 'is_active' => 1],
            ['id' => 17, 'tag_name' => 'Ekologie', 'slug' => 'ekologie', 'tag_type' => 'area', 'scope' => 'both', 'is_active' => 1],
            ['id' => 18, 'tag_name' => 'Data science', 'slug' => 'data-science', 'tag_type' => 'area', 'scope' => 'both', 'is_active' => 1],
            ['id' => 19, 'tag_name' => 'Networking', 'slug' => 'networking', 'tag_type' => 'area', 'scope' => 'both', 'is_active' => 1],
            ['id' => 20, 'tag_name' => 'Podnikání', 'slug' => 'podnikani', 'tag_type' => 'area', 'scope' => 'both', 'is_active' => 1],
            ['id' => 29, 'tag_name' => 'Veřejné', 'slug' => 'verejne', 'tag_type' => 'category', 'scope' => 'event', 'is_active' => 1],
            ['id' => 30, 'tag_name' => 'Komunitní', 'slug' => 'komunitni', 'tag_type' => 'category', 'scope' => 'event', 'is_active' => 1],
            ['id' => 31, 'tag_name' => 'Univerzitní', 'slug' => 'univerzitni', 'tag_type' => 'category', 'scope' => 'event', 'is_active' => 1],
        ];
    }

    private function tableIsEmpty(string $table): bool
    {
        $statement = $this->getAdapter()->getConnection()->query("SELECT COUNT(*) FROM $table");

        return $statement !== false && (int) $statement->fetchColumn() === 0;
    }
}
