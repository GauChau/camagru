CREATE TABLE images (
    id integer GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id integer NOT NULL REFERENCES users(id),
    file_key text UNIQUE NOT NULL,
    created_at timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP()
);