<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Elastica\Query\BoolQuery;
use Elastica\Query\Terms;
use EMS\CoreBundle\Entity\Form\SearchFilter;
use EMS\Helpers\Standard\Json;
use Ramsey\Uuid\Uuid;

final class Version20260919133300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cleaning of old legacy search entities + Advanced search dashboard + If needed, create an advanced search dashboard.';
    }

    #[\Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );

        $this->addSql('DROP SEQUENCE schema_demo_adm.search_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE schema_demo_adm.search_filter_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE schema_demo_adm.sort_option_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE schema_demo_adm.aggregate_option_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE schema_demo_adm.search_field_option_id_seq CASCADE');
        $this->addSql('ALTER TABLE search DROP CONSTRAINT fk_b4f0dba71a445520');
        $this->addSql('ALTER TABLE search_filter DROP CONSTRAINT fk_a6263002650760a9');
        $this->addSql('DROP TABLE aggregate_option');
        $this->addSql('DROP TABLE search');
        $this->addSql('DROP TABLE search_field_option');
        $this->addSql('DROP TABLE search_filter');
        $this->addSql('DROP TABLE sort_option');
        $this->addSql('ALTER TABLE content_type ADD query_search_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE content_type ADD CONSTRAINT FK_41BCBAEC936B6C19 FOREIGN KEY (query_search_id) REFERENCES query_search (id)');
        $this->addSql('CREATE INDEX IDX_41BCBAEC936B6C19 ON content_type (query_search_id)');
        $this->addSql('ALTER TABLE query_search ADD default_query_search BOOLEAN DEFAULT false NOT NULL');
        
        $hasAdvancedSearchOptions = (bool) $this->connection->fetchOne(<<<'SQL'
            SELECT EXISTS (
                SELECT 1
                FROM content_type
                WHERE roles IS NOT NULL
                  AND COALESCE(roles::jsonb ->> 'show_link_search', 'not-defined') <> 'not-defined'
            )
            OR EXISTS (SELECT 1 FROM sort_option)
            OR EXISTS (SELECT 1 FROM search_field_option)
            OR EXISTS (SELECT 1 FROM aggregate_option)
        SQL);

        if (!$hasAdvancedSearchOptions || $this->connection->fetchOne(
            'SELECT 1 FROM dashboard WHERE name = :name',
            ['name' => 'advanced_search']
        )) {
            return;
        }

        $sortOptions = \array_map(static fn (array $option): array => [
            'name' => $option['name'],
            'field' => $option['field'],
            'orderKey' => $option['orderkey'],
            'inverted' => $option['inverted'],
            'icon' => $option['icon'],
        ], $this->connection->fetchAllAssociative('SELECT name, field, orderkey, inverted, icon FROM sort_option ORDER BY orderkey'));
        $searchFieldOptions = \array_map(static fn (array $option): array => [
            'name' => $option['name'],
            'field' => $option['field'],
            'orderKey' => $option['orderkey'],
            'icon' => $option['icon'],
            'contentTypes' => Json::decode((string) $option['contenttypes']),
            'operators' => Json::decode((string) $option['operators']),
        ], $this->connection->fetchAllAssociative('SELECT name, field, orderkey, icon, contenttypes, operators FROM search_field_option ORDER BY orderkey'));
        $aggregateOptions = \array_map(static fn (array $option): array => [
            'name' => $option['name'],
            'config' => $option['config'],
            'template' => $option['template'],
            'orderKey' => $option['orderkey'],
            'icon' => $option['icon'],
        ], $this->connection->fetchAllAssociative('SELECT name, config, template, orderkey, icon FROM aggregate_option ORDER BY orderkey'));
        $connection = $this->connection;
        $searches = \array_map(static fn (array $search): array => [
            'id' => $search['id'],
            'name' => $search['name'],
            'environments' => $search['environments'],
            'contenttypes' => $search['contenttypes'],
            'sort_by' => $search['sort_by'],
            'sort_order' => $search['sort_order'],
            'default_search' => $search['default_search'],
            'minimum_should_match' => $search['minimum_should_match'] ?? 1,
            'contentTypeId' => $search['content_type_id'],
            'filters' => \array_map(static fn (array $filter): array => [
                'booleanClause' => $filter['boolean_clause'],
                'field' => $filter['field'],
                'operator' => $filter['operator'],
                'pattern' => $filter['pattern'],
                'boost' => $filter['boost'],
            ], $connection->fetchAllAssociative('SELECT boolean_clause, field, operator, pattern, boost FROM search_filter WHERE search_id = :id', ['id' => $search['id']])),
        ], $this->connection->fetchAllAssociative('SELECT id, name, environments, contenttypes, sort_by, sort_order, default_search, minimum_should_match, content_type_id FROM search'));

        $defaultEnvironments = $this->connection->fetchFirstColumn('SELECT name FROM environment WHERE in_default_search IS TRUE ORDER BY order_key');
        if ([] === $defaultEnvironments) {
            $defaultEnvironments = $this->connection->fetchFirstColumn('SELECT name FROM environment ORDER BY order_key');
        }
        $environmentsByName = [];
        foreach ($this->connection->fetchAllAssociative('SELECT id, name FROM environment') as $environment) {
            $environmentsByName[$environment['name']] = $environment['id'];
        }
        $defaultContentTypes = [];
        $defaultSortBy = null;
        $defaultSortOrder = null;
        $defaultFilters = [[
            'booleanClause' => 'must',
            'field' => '',
            'operator' => 'query_and',
            'pattern' => '',
            'boost' => '',
        ]];
        $defaultMinimumShouldMatch = 1;
        
        $configByContentTypeId = [];
        foreach ($searches as $search) {
            if ($search['default_search']) {
                $defaultEnvironments = Json::decode($search['environments']);
                $defaultContentTypes = Json::decode($search['contenttypes']);
                $defaultSortBy = $search['sort_by'];
                $defaultSortOrder = $search['sort_order'];
                $defaultFilters = $search['filters'];
                $defaultMinimumShouldMatch = $search['minimum_should_match'];
            }

            $boolQuery = new BoolQuery();
            $boolQuery->setMinimumShouldMatch($search['minimum_should_match'] ?? 1);
            $searchContentTypes = \array_values(array_filter(Json::decode($search['contenttypes']), fn($v) => \is_string($v)));
            if ([] !== $searchContentTypes) {
                $terms = new Terms('_contenttype');
                $terms->setTerms($searchContentTypes);
                $boolQuery->addMust($terms);
            }
            foreach ($search['filters'] as $filter) {
                if (!$filter['pattern']) {
                    $filter['pattern'] = '%query%';
                }
                $searchFilter = SearchFilter::fromArray($filter)->generateEsFilter();
                if (null === $searchFilter) {
                    continue;
                }
                switch ($filter['booleanClause']) {
                    case 'must':
                        $boolQuery->addMust($searchFilter);
                        break;
                    case 'should':
                        $boolQuery->addShould($searchFilter);
                        break;
                    case 'must_not':
                        $boolQuery->addMustNot($searchFilter);
                        break;
                    case 'filter':
                        $boolQuery->addFilter(new BoolQuery()->addMust($searchFilter));
                        break;
                    default:
                        throw new \RuntimeException(\sprintf('Unexpected %s boolean clause', $filter['booleanClause']));
                }
            }

            
            $query = [
                'query' => $boolQuery->toArray(),
            ];
            if ($search['sort_by']) {
                $query['sort'] = [[
                    $search['sort_by'] => $search['sort_order'] ?? 'asc',
                ]];
            }

            $id = Uuid::uuid4()->toString();
            $this->addSql(<<<'SQL'
                    INSERT INTO query_search (
                        id, created, modified, label, name, default_query_search, options, order_key
                    ) VALUES (
                        :id, NOW(), NOW(), :label, :name, :default_query_search, CAST(:options AS JSON), COALESCE((SELECT MAX(order_key) + 1 FROM query_search), 1)
                    )
                SQL, [
                'id' => $id,
                'label' => \sprintf('Migrated search "%s"', $search['name']),
                'name' => \sprintf('migrated_search_%s_%s', \strtolower($search['name']), substr(Uuid::uuid4()->toString(), -6)),
                'default_query_search' => $search['default_search'],
                'options' => Json::encode([
                    'query' => Json::encode($query),
                ]),
            ],
            [
                'default_query_search' => ParameterType::BOOLEAN,
            ]);

            $environmentQuerySearches = \array_map(fn($v) => [
                'query_search_id' => $id,
                'environment_id' => $environmentsByName[$v],
            ], array_values(Json::decode($search['environments'])));
            foreach ($environmentQuerySearches as $environmentQuerySearche) {
                $this->addSql(<<<'SQL'
                    INSERT INTO environment_query_search (
                        query_search_id, environment_id
                    ) VALUES (
                        :query_search_id, :environment_id
                    )
                SQL, $environmentQuerySearche);
            }
            
            if ($search['contentTypeId']) {
                $configByContentTypeId[$search['contentTypeId']] = [
                    'environments' => $search['environments'],
                    'filters' => $search['filters'],
                    'minimum_should_match' => $search['minimum_should_match'],
                    'sort_by' => $search['sort_by'],
                    'sort_order' => $search['sort_order'],
                ];
                
                $this->addSql(<<<'SQL'
                    UPDATE content_type SET query_search_id = :query_search_id
                    WHERE id = :id
                SQL, [
                    'id' => $search['contentTypeId'],
                    'query_search_id' => $id,
                ]);
            }
        }

        foreach ($connection->fetchAllAssociative("SELECT id, name, pluralname, singularname, roles ->> 'show_link_search' AS show_link_search FROM content_type  WHERE roles IS NOT NULL AND COALESCE(roles::jsonb ->> 'show_link_search', 'not-defined') <> 'not-defined'") as $contentType) {
            if (isset($configByContentTypeId[$contentType['id']])) {
                $sortBy = $configByContentTypeId[$contentType['id']]['sort_by'];
                $sortOrder = $configByContentTypeId[$contentType['id']]['sort_order'];
                $filters = $configByContentTypeId[$contentType['id']]['filters'];
                $minimumShouldMatch = $configByContentTypeId[$contentType['id']]['minimum_should_match'];
            } else {
                $sortBy = $defaultSortBy;
                $sortOrder = $defaultSortOrder;
                $filters = $defaultFilters;
                $minimumShouldMatch = $defaultMinimumShouldMatch;
            }

            $this->addSql(<<<'SQL'
                    INSERT INTO view (
                        id, content_type_id, created, modified, name, type, icon, label, role, public, options, order_key, definition
                    ) VALUES (
                        nextval('view_id_seq'), :contentTypeId, NOW(), NOW(), :name, 'ems.view.redirection', 'fa fa-search', :label,
                        :role, FALSE, CAST(:options AS JSON), -1, NULL
                    )
                SQL, [
                'contentTypeId' => $contentType['id'],
                'name' => \sprintf('search_in_%s', $contentType['name']),
                'label' => \sprintf('Search in %s', $contentType['pluralname']),
                'role' => $contentType['show_link_search'],
                'options' => Json::encode([
                    'template' => \sprintf(<<<'TWIG'
                                {%%- set data = {contentTypes:[view.contentType.name],environments:[view.contentType.environment.name],filters:%s,minimumShouldMatch:"%d",sortBy:"%s",sortOrder:"%s"} -%%}
                                {%%- set uid = emsco_save_contents(data|json_encode, 'search_%s.json', 'application/json', 1).sha1 -%%}
                                
                                {{- path('emsco_dashboard', {uid:uid, name:'advanced_search'}) -}}
                                TWIG, Json::encode($filters), $minimumShouldMatch, $sortBy, $sortOrder, $contentType['name']),
                ]),
            ]);
        }

        $this->addSql(<<<'SQL'
            INSERT INTO dashboard (
                id, created, modified, name, icon, label, sidebar_menu, notification_menu, definition, type, role, color, options, order_key
            ) VALUES (
                :id, NOW(), NOW(), 'advanced_search', 'fa fa-search', 'Search', TRUE, FALSE,
                CASE WHEN EXISTS (SELECT 1 FROM dashboard WHERE definition = 'quick_search') THEN NULL ELSE 'quick_search' END,
                'ems_core.dashboard.advanced_search', 'ROLE_USER', NULL, CAST(:options AS JSON), COALESCE((SELECT MAX(order_key) + 1 FROM dashboard), 1)
            )
        SQL, [
            'id' => Uuid::uuid4()->toString(),
            'options' => Json::encode([
                'environments' => $defaultEnvironments,
                'contentTypes' => $defaultContentTypes,
                'sortBy' => $defaultSortBy,
                'sortOrder' => $defaultSortOrder,
                'filters' => $defaultFilters,
                'minimum_should_match' => $defaultMinimumShouldMatch,
                'sortOptions' => $sortOptions,
                'searchFieldOptions' => $searchFieldOptions,
                'aggregateOptions' => $aggregateOptions,
            ]),
        ]);
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );

        $this->addSql('CREATE SEQUENCE schema_demo_adm.search_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE schema_demo_adm.search_filter_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE schema_demo_adm.sort_option_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE schema_demo_adm.aggregate_option_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE schema_demo_adm.search_field_option_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE aggregate_option (id INT NOT NULL, created TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, name VARCHAR(255) NOT NULL, config TEXT DEFAULT NULL, orderkey INT NOT NULL, template TEXT DEFAULT NULL, icon TEXT DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE search (id BIGINT NOT NULL, username VARCHAR(100) NOT NULL, environments JSON NOT NULL, contenttypes JSON NOT NULL, name VARCHAR(100) NOT NULL, sort_by VARCHAR(100) DEFAULT NULL, sort_order VARCHAR(100) DEFAULT NULL, default_search BOOLEAN DEFAULT false NOT NULL, content_type_id BIGINT DEFAULT NULL, minimum_should_match INT DEFAULT 1 NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_b4f0dba71a445520 ON search (content_type_id)');
        $this->addSql('CREATE TABLE search_field_option (id INT NOT NULL, created TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, name VARCHAR(255) NOT NULL, field TEXT NOT NULL, orderkey INT NOT NULL, icon TEXT DEFAULT NULL, contenttypes JSON NOT NULL, operators JSON NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE search_filter (id BIGINT NOT NULL, search_id BIGINT DEFAULT NULL, pattern VARCHAR(200) DEFAULT NULL, field VARCHAR(100) DEFAULT NULL, boolean_clause VARCHAR(20) DEFAULT NULL, operator VARCHAR(50) NOT NULL, boost NUMERIC(10, 2) DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_a6263002650760a9 ON search_filter (search_id)');
        $this->addSql('CREATE TABLE sort_option (id INT NOT NULL, created TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, name VARCHAR(255) NOT NULL, field TEXT NOT NULL, orderkey INT NOT NULL, inverted BOOLEAN NOT NULL, icon TEXT DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE search ADD CONSTRAINT fk_b4f0dba71a445520 FOREIGN KEY (content_type_id) REFERENCES content_type (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE search_filter ADD CONSTRAINT fk_a6263002650760a9 FOREIGN KEY (search_id) REFERENCES search (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE content_type DROP CONSTRAINT FK_41BCBAEC936B6C19');
        $this->addSql('DROP INDEX IDX_41BCBAEC936B6C19');
        $this->addSql('ALTER TABLE content_type DROP query_search_id');
        $this->addSql('ALTER TABLE query_search DROP default_query_search');

        $this->addSql('DELETE FROM dashboard WHERE name = :name', [
            'name' => 'advanced_search',
        ]);
    }
}
