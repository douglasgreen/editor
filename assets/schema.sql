CREATE TABLE document_version (
    document_version_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    page_index INT NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT ck_document_version_page_index_range
        CHECK (page_index BETWEEN 1 AND 10)
);

CREATE INDEX ix_document_version_page_index_created_at
    ON document_version (page_index, created_at DESC);

COMMENT ON TABLE document_version IS 'Stores versions of markdown document pages (1-10)';
COMMENT ON COLUMN document_version.page_index IS 'The page index (1-10)';
COMMENT ON COLUMN document_version.content IS 'The markdown content of the page';
COMMENT ON COLUMN document_version.created_at IS 'Timestamp of when the version was created';
