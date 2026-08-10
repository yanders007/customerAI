<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ══════════════════════════════════════════════════════════════
        //  INDEX POUR OPTIMISER LES REQUÊTES FRÉQUENTES
        // ══════════════════════════════════════════════════════════════
        
        // ── Conversations : filtrage par client_id (très fréquent) ───
        Schema::table('conversations', function (Blueprint $table) {
            if (!$this->indexExists('conversations', 'conversations_client_id_index')) {
                $table->index('client_id', 'conversations_client_id_index');
            }
            // Index sur status pour filtrer les conversations escaladées/ouvertes
            if (!$this->indexExists('conversations', 'conversations_status_index')) {
                $table->index('status', 'conversations_status_index');
            }
            // Index composite pour requêtes par client ET statut
            if (!$this->indexExists('conversations', 'conversations_client_status_index')) {
                $table->index(['client_id', 'status'], 'conversations_client_status_index');
            }
        });

        // ── Messages : filtrage par conversation_id (très fréquent) ──
        Schema::table('messages', function (Blueprint $table) {
            if (!$this->indexExists('messages', 'messages_conversation_id_index')) {
                $table->index('conversation_id', 'messages_conversation_id_index');
            }
            // Index sur role pour distinguer user/assistant/human_support rapidement
            if (!$this->indexExists('messages', 'messages_role_index')) {
                $table->index('role', 'messages_role_index');
            }
        });

        // ── DocumentationChunks : filtrage par documentation_id (RAG) ─
        Schema::table('documentation_chunks', function (Blueprint $table) {
            if (!$this->indexExists('documentation_chunks', 'documentation_chunks_documentation_id_index')) {
                $table->index('documentation_id', 'documentation_chunks_documentation_id_index');
            }
        });

        // ── Documentations : filtrage par projet_id (admin panel) ────
        Schema::table('documentations', function (Blueprint $table) {
            if (!$this->indexExists('documentations', 'documentations_projet_id_index')) {
                $table->index('projet_id', 'documentations_projet_id_index');
            }
        });

        // ── Projets : filtrage par client_id (admin panel) ───────────
        Schema::table('projets', function (Blueprint $table) {
            if (!$this->indexExists('projets', 'projets_client_id_index')) {
                $table->index('client_id', 'projets_client_id_index');
            }
        });

        // ── FAQs : filtrage par documentation_id (recherche FAQ) ─────
        Schema::table('faqs', function (Blueprint $table) {
            if (!$this->indexExists('faqs', 'faqs_documentation_id_index')) {
                $table->index('documentation_id', 'faqs_documentation_id_index');
            }
        });

        // ── Clients : recherche par email et client_identifier (login)
        Schema::table('clients', function (Blueprint $table) {
            // email déjà unique donc index automatique
            if (!$this->indexExists('clients', 'clients_client_identifier_index')) {
                $table->index('client_identifier', 'clients_client_identifier_index');
            }
            // Index sur last_login pour calculer rapidement is_active
            if (!$this->indexExists('clients', 'clients_last_login_index')) {
                $table->index('last_login', 'clients_last_login_index');
            }
        });

        // ── Admins : recherche par email (login) ─────────────────────
        // email déjà unique donc index automatique, pas besoin d'ajouter

        // ── Sessions : optimisation cleanup automatique ───────────────
        Schema::table('sessions', function (Blueprint $table) {
            // Index sur last_activity pour nettoyage des sessions expirées
            if (!$this->indexExists('sessions', 'sessions_last_activity_index')) {
                $table->index('last_activity', 'sessions_last_activity_index');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // ══════════════════════════════════════════════════════════════
        //  ROLLBACK : SUPPRESSION DES INDEX
        // ══════════════════════════════════════════════════════════════
        
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex('conversations_client_id_index');
            $table->dropIndex('conversations_status_index');
            $table->dropIndex('conversations_client_status_index');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('messages_conversation_id_index');
            $table->dropIndex('messages_role_index');
        });

        Schema::table('documentation_chunks', function (Blueprint $table) {
            $table->dropIndex('documentation_chunks_documentation_id_index');
        });

        Schema::table('documentations', function (Blueprint $table) {
            $table->dropIndex('documentations_projet_id_index');
        });

        Schema::table('projets', function (Blueprint $table) {
            $table->dropIndex('projets_client_id_index');
        });

        Schema::table('faqs', function (Blueprint $table) {
            $table->dropIndex('faqs_documentation_id_index');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex('clients_client_identifier_index');
            $table->dropIndex('clients_last_login_index');
        });

        Schema::table('sessions', function (Blueprint $table) {
            $table->dropIndex('sessions_last_activity_index');
        });
    }

    /**
     * Vérifie si un index existe déjà
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $databaseName = $connection->getDatabaseName();
        
        // Pour SQLite (dev)
        if ($connection->getDriverName() === 'sqlite') {
            $indexes = $connection->select("PRAGMA index_list({$table})");
            foreach ($indexes as $index) {
                if ($index->name === $indexName) {
                    return true;
                }
            }
            return false;
        }
        
        // Pour MySQL/MariaDB (prod)
        if ($connection->getDriverName() === 'mysql') {
            $result = $connection->select(
                "SELECT COUNT(*) as count FROM information_schema.statistics 
                 WHERE table_schema = ? AND table_name = ? AND index_name = ?",
                [$databaseName, $table, $indexName]
            );
            return !empty($result) && $result[0]->count > 0;
        }
        
        // Pour PostgreSQL (prod)
        if ($connection->getDriverName() === 'pgsql') {
            $result = $connection->select(
                "SELECT COUNT(*) as count FROM pg_indexes 
                 WHERE schemaname = 'public' AND tablename = ? AND indexname = ?",
                [$table, $indexName]
            );
            return !empty($result) && $result[0]->count > 0;
        }
        
        return false;
    }
};
