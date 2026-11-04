-- À appliquer aussi sur une base existante (voir README.md).
ALTER TABLE users ADD COLUMN IF NOT EXISTS comment_notifications boolean NOT NULL DEFAULT TRUE;

CREATE TABLE IF NOT EXISTS image_likes (
    image_id integer NOT NULL REFERENCES images(id) ON DELETE CASCADE,
    user_id integer NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    PRIMARY KEY (image_id, user_id)
);

CREATE TABLE IF NOT EXISTS image_comments (
    id integer GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    image_id integer NOT NULL REFERENCES images(id) ON DELETE CASCADE,
    user_id integer NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    body text NOT NULL CHECK (char_length(btrim(body)) BETWEEN 1 AND 2000),
    created_at timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS image_comments_image_date ON image_comments (image_id, created_at, id);
CREATE INDEX IF NOT EXISTS images_creation_date ON images (created_at DESC, id DESC);
