<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class MigrateToLumioSchemaV2 extends AbstractMigration
{
    public function up(): void
    {
        $this->execute('SET FOREIGN_KEY_CHECKS = 0');

        try {
            $this->ensureRoles();
            $this->ensureUsers();
            $this->ensureExperts();
            $this->ensureTags();
            $this->ensureEvents();
            $this->ensureAttendees();
            $this->ensureAudienceRoles();
            $this->ensureEventTags();
            $this->ensureExpertTags();
            $this->ensurePasswordResets();
            $this->migrateLegacyTagLinks();
            $this->backfillEventAudienceRoles();
            $this->dropLegacyTables();
        } finally {
            $this->execute('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Schema v2 migration is not reversible without data loss.');
    }

    private function ensureRoles(): void
    {
        if (!$this->hasTable('roles')) {
            $this->table('roles', ['id' => false, 'primary_key' => ['code']])
                ->addColumn('code', 'string', ['limit' => 32, 'null' => false])
                ->addColumn('label', 'string', ['limit' => 64, 'null' => false])
                ->create();
        }

        $this->execute(
            "INSERT INTO roles (code, label) VALUES
                ('student', 'Student ZČU'),
                ('absolvent', 'Absolvent ZČU'),
                ('zamestnanec', 'Zaměstnanec ZČU'),
                ('stredoskolak', 'Žák SŠ'),
                ('jine', 'Ostatní')
            ON DUPLICATE KEY UPDATE label = VALUES(label)"
        );

        if ($this->hasTable('users') && $this->columnExists('users', 'role')) {
            $this->execute(
                "INSERT IGNORE INTO roles (code, label)
                SELECT DISTINCT role, role
                FROM users
                WHERE role IS NOT NULL AND TRIM(role) <> ''"
            );
        }
    }

    private function ensureUsers(): void
    {
        if (!$this->hasTable('users')) {
            $this->execute(
                "CREATE TABLE users (
                    id INT NOT NULL AUTO_INCREMENT,
                    name VARCHAR(45) NOT NULL,
                    role VARCHAR(32) NOT NULL,
                    faculty ENUM('FAV', 'FDU', 'FEK', 'FEL', 'FF', 'FPE', 'FPR', 'FST', 'FZS') NULL,
                    active TINYINT(1) NOT NULL DEFAULT 1,
                    password VARCHAR(256) NOT NULL,
                    email VARCHAR(100) NOT NULL,
                    admin TINYINT(1) NOT NULL DEFAULT 0,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_users_email (email),
                    KEY fk_users_roles1_idx (role),
                    CONSTRAINT fk_users_roles1
                        FOREIGN KEY (role) REFERENCES roles (code)
                        ON DELETE NO ACTION ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
            return;
        }

        $this->execute("UPDATE users SET role = 'student' WHERE role IS NULL OR TRIM(role) = ''");

        if ($this->columnExists('users', 'faculty')) {
            $this->execute("ALTER TABLE users MODIFY faculty ENUM('FAV', 'FDULS', 'FDU', 'FEK', 'FEL', 'FF', 'FPE', 'FPR', 'FST', 'FZS') NULL");
            $this->execute("UPDATE users SET faculty = 'FDU' WHERE faculty = 'FDULS'");
        }

        foreach ([
            'faculty' => "ALTER TABLE users ADD faculty ENUM('FAV', 'FDU', 'FEK', 'FEL', 'FF', 'FPE', 'FPR', 'FST', 'FZS') NULL",
            'active' => "ALTER TABLE users ADD active TINYINT(1) NOT NULL DEFAULT 1",
            'password' => "ALTER TABLE users ADD password VARCHAR(256) NOT NULL DEFAULT ''",
            'email' => "ALTER TABLE users ADD email VARCHAR(100) NOT NULL DEFAULT ''",
            'admin' => "ALTER TABLE users ADD admin TINYINT(1) NOT NULL DEFAULT 0",
        ] as $column => $sql) {
            if (!$this->columnExists('users', $column)) {
                $this->execute($sql);
            }
        }

        $this->execute("UPDATE users SET name = CONCAT('User ', id) WHERE name IS NULL OR TRIM(name) = ''");
        $this->execute("UPDATE users SET active = 1 WHERE active IS NULL");
        $this->execute("UPDATE users SET admin = 0 WHERE admin IS NULL");
        $this->execute("UPDATE users SET password = '' WHERE password IS NULL");
        $this->execute("UPDATE users SET email = CONCAT('user', id, '@lumio.invalid') WHERE email IS NULL OR TRIM(email) = ''");
        $this->deduplicateUserEmails();

        $this->execute("ALTER TABLE users MODIFY name VARCHAR(45) NOT NULL");
        $this->execute("ALTER TABLE users MODIFY role VARCHAR(32) NOT NULL");
        $this->execute("ALTER TABLE users MODIFY faculty ENUM('FAV', 'FDU', 'FEK', 'FEL', 'FF', 'FPE', 'FPR', 'FST', 'FZS') NULL");
        $this->execute("ALTER TABLE users MODIFY active TINYINT(1) NOT NULL DEFAULT 1");
        $this->execute("ALTER TABLE users MODIFY password VARCHAR(256) NOT NULL");
        $this->execute("ALTER TABLE users MODIFY email VARCHAR(100) NOT NULL");
        $this->execute("ALTER TABLE users MODIFY admin TINYINT(1) NOT NULL DEFAULT 0");

        $this->addIndexIfMissing('users', 'fk_users_roles1_idx', ['role']);
        $this->addIndexIfMissing('users', 'uq_users_email', ['email'], true);
        $this->addForeignKeyIfMissing('users', 'fk_users_roles1', 'role', 'roles', 'code', 'NO ACTION', 'CASCADE');
    }

    private function ensureExperts(): void
    {
        if (!$this->hasTable('experts')) {
            $this->table('experts')
                ->addColumn('name', 'string', ['limit' => 90, 'null' => false])
                ->addColumn('institution', 'string', ['limit' => 90, 'null' => true])
                ->addColumn('residence', 'string', ['limit' => 90, 'null' => true])
                ->addColumn('email', 'string', ['limit' => 90, 'null' => true])
                ->addColumn('telephone', 'string', ['limit' => 45, 'null' => true])
                ->addColumn('active', 'boolean', ['null' => false, 'default' => true])
                ->addColumn('note', 'string', ['limit' => 255, 'null' => true])
                ->create();
        }

        foreach ([
            'institution' => "ALTER TABLE experts ADD institution VARCHAR(90) NULL",
            'residence' => "ALTER TABLE experts ADD residence VARCHAR(90) NULL",
            'email' => "ALTER TABLE experts ADD email VARCHAR(90) NULL",
            'telephone' => "ALTER TABLE experts ADD telephone VARCHAR(45) NULL",
            'active' => "ALTER TABLE experts ADD active TINYINT(1) NOT NULL DEFAULT 1",
            'note' => "ALTER TABLE experts ADD note VARCHAR(255) NULL",
        ] as $column => $sql) {
            if (!$this->columnExists('experts', $column)) {
                $this->execute($sql);
            }
        }

        if ($this->hasTable('specialists')) {
            $this->execute(
                "INSERT INTO experts (id, name, institution, residence, email, telephone, active, note)
                SELECT
                    s.id,
                    COALESCE(NULLIF(TRIM(s.name), ''), CONCAT('Expert ', s.id)),
                    s.institution,
                    s.residence,
                    s.email,
                    s.telephone,
                    1,
                    s.note
                FROM specialists s
                WHERE NOT EXISTS (
                    SELECT 1 FROM experts e WHERE e.id = s.id
                )"
            );
        }

        $this->execute("UPDATE experts SET name = CONCAT('Expert ', id) WHERE name IS NULL OR TRIM(name) = ''");
        $this->execute("UPDATE experts SET active = 1 WHERE active IS NULL");
        $this->execute("ALTER TABLE experts MODIFY name VARCHAR(90) NOT NULL");
        $this->execute("ALTER TABLE experts MODIFY institution VARCHAR(90) NULL");
        $this->execute("ALTER TABLE experts MODIFY residence VARCHAR(90) NULL");
        $this->execute("ALTER TABLE experts MODIFY email VARCHAR(90) NULL");
        $this->execute("ALTER TABLE experts MODIFY telephone VARCHAR(45) NULL");
        $this->execute("ALTER TABLE experts MODIFY active TINYINT(1) NOT NULL DEFAULT 1");
        $this->execute("ALTER TABLE experts MODIFY note VARCHAR(255) NULL");
        $this->execute("ALTER TABLE experts MODIFY id INT NOT NULL AUTO_INCREMENT");
    }

    private function ensureTags(): void
    {
        if (!$this->hasTable('tags')) {
            $this->table('tags')
                ->addColumn('tag_name', 'string', ['limit' => 90, 'null' => false])
                ->addColumn('slug', 'string', ['limit' => 120, 'null' => true])
                ->addColumn('tag_type', 'string', ['limit' => 16, 'null' => false, 'default' => 'area'])
                ->addColumn('scope', 'string', ['limit' => 16, 'null' => false, 'default' => 'both'])
                ->addColumn('is_active', 'boolean', ['null' => false, 'default' => true])
                ->create();
        }

        if (!$this->columnExists('tags', 'slug')) {
            $this->execute('ALTER TABLE tags ADD slug VARCHAR(120) NULL');
        }
        if (!$this->columnExists('tags', 'tag_type')) {
            $this->execute("ALTER TABLE tags ADD tag_type VARCHAR(16) NULL");
        }
        if (!$this->columnExists('tags', 'scope')) {
            $this->execute("ALTER TABLE tags ADD scope VARCHAR(16) NULL");
        }
        if (!$this->columnExists('tags', 'is_active')) {
            $this->execute("ALTER TABLE tags ADD is_active TINYINT(1) NOT NULL DEFAULT 1");
        }

        if ($this->columnExists('tags', 'role_tag')) {
            $this->execute(
                "UPDATE tags
                SET tag_type = CASE WHEN role_tag = 0 THEN 'category' ELSE 'area' END,
                    scope = CASE WHEN role_tag = 0 THEN 'event' ELSE 'both' END
                WHERE tag_type IS NULL OR scope IS NULL"
            );
        }

        $this->execute("UPDATE tags SET tag_name = CONCAT('Tag ', id) WHERE tag_name IS NULL OR TRIM(tag_name) = ''");
        $this->execute("UPDATE tags SET tag_type = 'area' WHERE tag_type IS NULL OR tag_type NOT IN ('area', 'category')");
        $this->execute("UPDATE tags SET scope = 'both' WHERE scope IS NULL OR scope NOT IN ('event', 'expert', 'both')");
        $this->execute("UPDATE tags SET is_active = 1 WHERE is_active IS NULL");

        $this->ensureTagSlugs();

        $this->execute("ALTER TABLE tags MODIFY tag_name VARCHAR(90) NOT NULL");
        $this->execute("ALTER TABLE tags MODIFY slug VARCHAR(120) NOT NULL");
        $this->execute("ALTER TABLE tags MODIFY tag_type ENUM('area', 'category') NOT NULL");
        $this->execute("ALTER TABLE tags MODIFY scope ENUM('event', 'expert', 'both') NOT NULL DEFAULT 'both'");
        $this->execute("ALTER TABLE tags MODIFY is_active TINYINT(1) NOT NULL DEFAULT 1");

        $this->addIndexIfMissing('tags', 'uq_tags_slug', ['slug'], true);
        $this->addIndexIfMissing('tags', 'idx_tags_type_scope_active', ['tag_type', 'scope', 'is_active']);

        if ($this->columnExists('tags', 'role_tag')) {
            $this->execute('ALTER TABLE tags DROP COLUMN role_tag');
        }
    }

    private function ensureEvents(): void
    {
        if (!$this->hasTable('events')) {
            $this->execute(
                "CREATE TABLE events (
                    id INT NOT NULL AUTO_INCREMENT,
                    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
                    name VARCHAR(100) NOT NULL,
                    description TEXT NOT NULL,
                    datetime DATETIME NOT NULL,
                    image_path VARCHAR(255) NULL,
                    organiser VARCHAR(90) NOT NULL,
                    organiser_id INT NULL,
                    file_path VARCHAR(255) NULL,
                    event_report_path VARCHAR(255) NULL,
                    expert_id INT NULL,
                    PRIMARY KEY (id),
                    KEY fk_events_users1_idx (organiser_id),
                    KEY fk_events_experts1_idx (expert_id),
                    CONSTRAINT fk_events_users1 FOREIGN KEY (organiser_id) REFERENCES users (id)
                        ON DELETE NO ACTION ON UPDATE NO ACTION,
                    CONSTRAINT fk_events_experts1 FOREIGN KEY (expert_id) REFERENCES experts (id)
                        ON DELETE SET NULL ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
            return;
        }

        foreach ([
            'status' => "ALTER TABLE events ADD status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending'",
            'description' => "ALTER TABLE events ADD description TEXT NULL",
            'datetime' => "ALTER TABLE events ADD datetime DATETIME NULL",
            'image_path' => "ALTER TABLE events ADD image_path VARCHAR(255) NULL",
            'organiser' => "ALTER TABLE events ADD organiser VARCHAR(90) NULL",
            'organiser_id' => "ALTER TABLE events ADD organiser_id INT NULL",
            'file_path' => "ALTER TABLE events ADD file_path VARCHAR(255) NULL",
            'event_report_path' => "ALTER TABLE events ADD event_report_path VARCHAR(255) NULL",
            'expert_id' => "ALTER TABLE events ADD expert_id INT NULL",
        ] as $column => $sql) {
            if (!$this->columnExists('events', $column)) {
                $this->execute($sql);
            }
        }

        $this->execute("ALTER TABLE events MODIFY status ENUM('pending', 'approved', 'rejected', 'accepted', 'refused') NOT NULL DEFAULT 'pending'");

        if ($this->columnExists('events', 'users_id')) {
            $this->execute("UPDATE events SET organiser_id = users_id WHERE organiser_id IS NULL AND users_id IS NOT NULL");
        }

        $this->execute("UPDATE events SET name = CONCAT('Event ', id) WHERE name IS NULL OR TRIM(name) = ''");
        $this->execute("UPDATE events SET status = 'approved' WHERE status = 'accepted'");
        $this->execute("UPDATE events SET status = 'rejected' WHERE status = 'refused'");
        $this->execute("UPDATE events SET status = 'pending' WHERE status IS NULL OR status NOT IN ('pending', 'approved', 'rejected')");
        $this->execute("UPDATE events SET description = '' WHERE description IS NULL");
        $this->execute("UPDATE events SET organiser = '' WHERE organiser IS NULL");
        $this->execute("UPDATE events SET datetime = NOW() WHERE datetime IS NULL");
        $this->execute(
            "UPDATE events e
            LEFT JOIN users u ON u.id = e.organiser_id
            SET e.organiser_id = NULL
            WHERE e.organiser_id IS NOT NULL AND u.id IS NULL"
        );
        $this->execute(
            "UPDATE events e
            LEFT JOIN experts x ON x.id = e.expert_id
            SET e.expert_id = NULL
            WHERE e.expert_id IS NOT NULL AND x.id IS NULL"
        );

        $this->execute("ALTER TABLE events MODIFY name VARCHAR(100) NOT NULL");
        $this->execute("ALTER TABLE events MODIFY status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending'");
        $this->execute("ALTER TABLE events MODIFY description TEXT NOT NULL");
        $this->execute("ALTER TABLE events MODIFY datetime DATETIME NOT NULL");
        $this->execute("ALTER TABLE events MODIFY image_path VARCHAR(255) NULL");
        $this->execute("ALTER TABLE events MODIFY organiser VARCHAR(90) NOT NULL");
        $this->execute("ALTER TABLE events MODIFY organiser_id INT NULL");
        $this->execute("ALTER TABLE events MODIFY file_path VARCHAR(255) NULL");
        $this->execute("ALTER TABLE events MODIFY event_report_path VARCHAR(255) NULL");
        $this->execute("ALTER TABLE events MODIFY expert_id INT NULL");

        $this->addIndexIfMissing('events', 'fk_events_users1_idx', ['organiser_id']);
        $this->addIndexIfMissing('events', 'fk_events_experts1_idx', ['expert_id']);
        $this->addForeignKeyIfMissing('events', 'fk_events_users1', 'organiser_id', 'users', 'id', 'NO ACTION', 'NO ACTION');
        $this->addForeignKeyIfMissing('events', 'fk_events_experts1', 'expert_id', 'experts', 'id', 'SET NULL', 'CASCADE');

        if ($this->columnExists('events', 'users_id')) {
            $this->execute('ALTER TABLE events DROP COLUMN users_id');
        }
    }

    private function ensureAttendees(): void
    {
        if (!$this->hasTable('attendees')) {
            $this->table('attendees')
                ->addColumn('users_id', 'integer', ['null' => false, 'signed' => true])
                ->addColumn('events_id', 'integer', ['null' => false, 'signed' => true])
                ->addIndex(['users_id'], ['name' => 'fk_attendees_users1_idx'])
                ->addIndex(['events_id'], ['name' => 'fk_attendees_events1_idx'])
                ->addIndex(['users_id', 'events_id'], ['unique' => true, 'name' => 'uq_attendees_user_event'])
                ->addForeignKey('users_id', 'users', 'id', ['delete' => 'NO_ACTION', 'update' => 'NO_ACTION', 'constraint' => 'fk_attendees_users1'])
                ->addForeignKey('events_id', 'events', 'id', ['delete' => 'NO_ACTION', 'update' => 'NO_ACTION', 'constraint' => 'fk_attendees_events1'])
                ->create();
            return;
        }

        $this->execute(
            "DELETE a FROM attendees a
            LEFT JOIN users u ON u.id = a.users_id
            LEFT JOIN events e ON e.id = a.events_id
            WHERE u.id IS NULL OR e.id IS NULL"
        );
        $this->execute(
            "DELETE newer FROM attendees newer
            INNER JOIN attendees older
                ON older.users_id = newer.users_id
                AND older.events_id = newer.events_id
                AND older.id < newer.id"
        );

        $this->addIndexIfMissing('attendees', 'fk_attendees_users1_idx', ['users_id']);
        $this->addIndexIfMissing('attendees', 'fk_attendees_events1_idx', ['events_id']);
        $this->addIndexIfMissing('attendees', 'uq_attendees_user_event', ['users_id', 'events_id'], true);
        $this->addForeignKeyIfMissing('attendees', 'fk_attendees_users1', 'users_id', 'users', 'id', 'NO ACTION', 'NO ACTION');
        $this->addForeignKeyIfMissing('attendees', 'fk_attendees_events1', 'events_id', 'events', 'id', 'NO ACTION', 'NO ACTION');
    }

    private function ensureAudienceRoles(): void
    {
        if (!$this->hasTable('event_audience_roles')) {
            $this->table('event_audience_roles', ['id' => false, 'primary_key' => ['event_id', 'role']])
                ->addColumn('event_id', 'integer', ['null' => false, 'signed' => true])
                ->addColumn('role', 'string', ['limit' => 32, 'null' => false])
                ->addIndex(['role'], ['name' => 'fk_event_audience_roles_roles1_idx'])
                ->addForeignKey('event_id', 'events', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE', 'constraint' => 'fk_event_audience_roles_events1'])
                ->addForeignKey('role', 'roles', 'code', ['delete' => 'NO_ACTION', 'update' => 'CASCADE', 'constraint' => 'fk_event_audience_roles_roles1'])
                ->create();
        }
    }

    private function ensureEventTags(): void
    {
        if (!$this->hasTable('event_tags')) {
            $this->table('event_tags', ['id' => false, 'primary_key' => ['event_id', 'tag_id']])
                ->addColumn('event_id', 'integer', ['null' => false, 'signed' => true])
                ->addColumn('tag_id', 'integer', ['null' => false, 'signed' => true])
                ->addIndex(['tag_id'], ['name' => 'fk_event_tags_tags1_idx'])
                ->addForeignKey('event_id', 'events', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE', 'constraint' => 'fk_event_tags_events1'])
                ->addForeignKey('tag_id', 'tags', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE', 'constraint' => 'fk_event_tags_tags1'])
                ->create();
        }
    }

    private function ensureExpertTags(): void
    {
        if (!$this->hasTable('expert_tags')) {
            $this->table('expert_tags', ['id' => false, 'primary_key' => ['expert_id', 'tag_id']])
                ->addColumn('expert_id', 'integer', ['null' => false, 'signed' => true])
                ->addColumn('tag_id', 'integer', ['null' => false, 'signed' => true])
                ->addIndex(['tag_id'], ['name' => 'fk_expert_tags_tags1_idx'])
                ->addForeignKey('expert_id', 'experts', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE', 'constraint' => 'fk_expert_tags_experts1'])
                ->addForeignKey('tag_id', 'tags', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE', 'constraint' => 'fk_expert_tags_tags1'])
                ->create();
        }
    }

    private function ensurePasswordResets(): void
    {
        if (!$this->hasTable('password_resets')) {
            $this->table('password_resets', ['id' => false, 'primary_key' => ['user_id']])
                ->addColumn('user_id', 'integer', ['null' => false, 'signed' => true])
                ->addColumn('reset_code', 'string', ['limit' => 32, 'null' => false])
                ->addForeignKey('user_id', 'users', 'id', ['delete' => 'NO_ACTION', 'update' => 'NO_ACTION', 'constraint' => 'fk_password_resets_users1'])
                ->create();
        }
    }

    private function migrateLegacyTagLinks(): void
    {
        if (!$this->hasTable('tags_to_id')) {
            return;
        }

        $this->execute(
            "INSERT IGNORE INTO event_tags (event_id, tag_id)
            SELECT l.event_id, l.tag_id
            FROM tags_to_id l
            INNER JOIN events e ON e.id = l.event_id
            INNER JOIN tags t ON t.id = l.tag_id
            WHERE l.event_id IS NOT NULL"
        );

        $this->execute(
            "INSERT IGNORE INTO expert_tags (expert_id, tag_id)
            SELECT l.specialist_id, l.tag_id
            FROM tags_to_id l
            INNER JOIN experts e ON e.id = l.specialist_id
            INNER JOIN tags t ON t.id = l.tag_id
            WHERE l.specialist_id IS NOT NULL"
        );
    }

    private function backfillEventAudienceRoles(): void
    {
        $this->execute(
            "INSERT IGNORE INTO event_audience_roles (event_id, role)
            SELECT e.id, r.code
            FROM events e
            CROSS JOIN roles r
            LEFT JOIN event_audience_roles existing ON existing.event_id = e.id
            WHERE existing.event_id IS NULL"
        );
    }

    private function dropLegacyTables(): void
    {
        if ($this->hasTable('tags_to_id')) {
            $this->table('tags_to_id')->drop()->save();
        }

        if ($this->hasTable('specialists')) {
            $this->table('specialists')->drop()->save();
        }
    }

    private function deduplicateUserEmails(): void
    {
        $rows = $this->fetchRows('SELECT id, email FROM users ORDER BY id');
        $used = [];
        $statement = $this->pdo()->prepare('UPDATE users SET email = :email WHERE id = :id');

        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $email = trim((string) $row['email']);

            if ($email === '') {
                $email = 'user' . $id . '@lumio.invalid';
            }

            $candidate = $email;
            $suffix = 0;
            while (isset($used[strtolower($candidate)])) {
                $suffix++;
                $candidate = 'user' . $id . ($suffix > 1 ? '-' . $suffix : '') . '@lumio.invalid';
            }

            $used[strtolower($candidate)] = true;
            if ($candidate !== $email) {
                $statement->execute(['email' => $candidate, 'id' => $id]);
            }
        }
    }

    private function ensureTagSlugs(): void
    {
        $rows = $this->fetchRows('SELECT id, tag_name, slug FROM tags ORDER BY id');
        $used = [];

        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $baseSlug = trim((string) ($row['slug'] ?? ''));

            if ($baseSlug === '') {
                $baseSlug = $this->slugify((string) $row['tag_name']);
            } else {
                $baseSlug = $this->slugify($baseSlug);
            }

            if ($baseSlug === '') {
                $baseSlug = 'tag-' . $id;
            }

            $slug = $baseSlug;
            if (isset($used[$slug])) {
                $slug = $baseSlug . '-' . $id;
            }

            $used[$slug] = true;
            $statement = $this->pdo()->prepare('UPDATE tags SET slug = :slug WHERE id = :id');
            $statement->execute(['slug' => $slug, 'id' => $id]);
        }
    }

    private function slugify(string $value): string
    {
        $ascii = function_exists('iconv') ? @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) : $value;
        if ($ascii === false) {
            $ascii = $value;
        }

        $slug = strtolower((string) $ascii);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';

        return trim($slug, '-');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchRows(string $sql): array
    {
        $statement = $this->pdo()->query($sql);
        if ($statement === false) {
            return [];
        }

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function addIndexIfMissing(string $table, string $name, array $columns, bool $unique = false): void
    {
        if ($this->indexExists($table, $name)) {
            return;
        }

        $this->table($table)
            ->addIndex($columns, ['name' => $name, 'unique' => $unique])
            ->update();
    }

    private function addForeignKeyIfMissing(
        string $table,
        string $name,
        string $column,
        string $referencedTable,
        string $referencedColumn,
        string $onDelete,
        string $onUpdate
    ): void {
        if ($this->foreignKeyExists($table, $name)) {
            return;
        }

        $this->execute(sprintf(
            'ALTER TABLE `%s` ADD CONSTRAINT `%s` FOREIGN KEY (`%s`) REFERENCES `%s` (`%s`) ON DELETE %s ON UPDATE %s',
            $table,
            $name,
            $column,
            $referencedTable,
            $referencedColumn,
            $onDelete,
            $onUpdate
        ));
    }

    private function columnExists(string $table, string $column): bool
    {
        $statement = $this->pdo()->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = :table
                AND COLUMN_NAME = :column'
        );
        $statement->execute(['table' => $table, 'column' => $column]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function indexExists(string $table, string $name): bool
    {
        $statement = $this->pdo()->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = :table
                AND INDEX_NAME = :name'
        );
        $statement->execute(['table' => $table, 'name' => $name]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function foreignKeyExists(string $table, string $name): bool
    {
        $statement = $this->pdo()->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE()
                AND TABLE_NAME = :table
                AND CONSTRAINT_NAME = :name'
        );
        $statement->execute(['table' => $table, 'name' => $name]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function pdo(): PDO
    {
        return $this->getAdapter()->getConnection();
    }
}
