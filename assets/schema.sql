USE nurdsite_editor;

CREATE TABLE document_version (
    document_version_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    page_index INT NOT NULL COMMENT 'The page index (1-10)',
    content TEXT NOT NULL COMMENT 'The markdown content of the page',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Timestamp of when the version was created',

    CONSTRAINT ck_document_version_page_index_range
        CHECK (page_index BETWEEN 1 AND 10)
) COMMENT='Stores versions of markdown document pages (1-10)';

CREATE INDEX ix_document_version_page_index_created_at
    ON document_version (page_index, created_at);
