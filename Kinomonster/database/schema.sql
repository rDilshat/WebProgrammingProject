-- KinoMonster — схема базы данных (PostgreSQL)
--
-- Запуск (из папки Kinomonster):
--   psql -h 127.0.0.1 -U kinomonster_app -d kinomonster -f database/schema.sql
--   psql -h 127.0.0.1 -U kinomonster_app -d kinomonster -f database/seed.sql
--
-- ВНИМАНИЕ: файл пересоздаёт все таблицы с нуля (старые тестовые данные удаляются).
--
-- 10 таблиц:
--   users ──┬── password_reset_tokens
--           ├── bookings ── booking_seats
--           └── reviews
--   genres ── movie_genres ── movies ──┬── showtimes ── halls
--                                      └── reviews

DROP TABLE IF EXISTS booking_seats CASCADE;
DROP TABLE IF EXISTS bookings CASCADE;
DROP TABLE IF EXISTS reviews CASCADE;
DROP TABLE IF EXISTS showtimes CASCADE;
DROP TABLE IF EXISTS halls CASCADE;
DROP TABLE IF EXISTS movie_genres CASCADE;
DROP TABLE IF EXISTS genres CASCADE;
DROP TABLE IF EXISTS movies CASCADE;
DROP TABLE IF EXISTS password_reset_tokens CASCADE;
DROP TABLE IF EXISTS users CASCADE;

-- 1. Пользователи
CREATE TABLE users (
    id BIGSERIAL PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'user' CHECK (role IN ('user', 'admin')),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- 2. Токены восстановления пароля
CREATE TABLE password_reset_tokens (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash VARCHAR(64) NOT NULL UNIQUE,
    expires_at TIMESTAMPTZ NOT NULL,
    used_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX password_reset_tokens_user_id_idx ON password_reset_tokens (user_id);

-- 3. Жанры
CREATE TABLE genres (
    id SERIAL PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE
);

-- 4. Фильмы и сериалы
--    slug — короткий id для адресов: movie.php?id=joker
--    trailer_url — ссылка на YouTube (https://www.youtube.com/watch?v=...), может быть пустой
CREATE TABLE movies (
    id BIGSERIAL PRIMARY KEY,
    slug VARCHAR(100) NOT NULL UNIQUE,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL DEFAULT '',
    duration_min INTEGER NOT NULL CHECK (duration_min > 0),
    release_year INTEGER CHECK (release_year BETWEEN 1888 AND 2100),
    poster VARCHAR(255) NOT NULL,
    trailer_url VARCHAR(255),
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- 5. Связь фильм ↔ жанр (многие ко многим)
CREATE TABLE movie_genres (
    movie_id BIGINT NOT NULL REFERENCES movies(id) ON DELETE CASCADE,
    genre_id INTEGER NOT NULL REFERENCES genres(id) ON DELETE CASCADE,
    PRIMARY KEY (movie_id, genre_id)
);

-- 6. Залы
CREATE TABLE halls (
    id SERIAL PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    capacity INTEGER NOT NULL CHECK (capacity > 0)
);

-- 7. Сеансы: какой фильм, в каком зале, когда и за сколько
CREATE TABLE showtimes (
    id BIGSERIAL PRIMARY KEY,
    movie_id BIGINT NOT NULL REFERENCES movies(id) ON DELETE CASCADE,
    hall_id INTEGER NOT NULL REFERENCES halls(id) ON DELETE RESTRICT,
    starts_at TIMESTAMP NOT NULL,
    price NUMERIC(8, 2) NOT NULL CHECK (price >= 0),
    UNIQUE (movie_id, starts_at)
);

CREATE INDEX showtimes_starts_at_idx ON showtimes (starts_at);

-- 8. Бронирования
CREATE TABLE bookings (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    showtime_id BIGINT NOT NULL REFERENCES showtimes(id) ON DELETE CASCADE,
    status VARCHAR(20) NOT NULL DEFAULT 'confirmed'
        CHECK (status IN ('pending', 'confirmed', 'cancelled')),
    total_price NUMERIC(10, 2) NOT NULL CHECK (total_price >= 0),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX bookings_user_id_idx ON bookings (user_id);
CREATE INDEX bookings_showtime_id_idx ON bookings (showtime_id);

-- 9. Места в бронировании
--    is_active = FALSE, когда бронь отменена — место снова свободно.
--    Уникальный индекс не даёт занять одно активное место на сеансе дважды.
CREATE TABLE booking_seats (
    id BIGSERIAL PRIMARY KEY,
    booking_id BIGINT NOT NULL REFERENCES bookings(id) ON DELETE CASCADE,
    showtime_id BIGINT NOT NULL REFERENCES showtimes(id) ON DELETE CASCADE,
    seat_number INTEGER NOT NULL CHECK (seat_number > 0),
    is_active BOOLEAN NOT NULL DEFAULT TRUE
);

CREATE UNIQUE INDEX booking_seats_active_seat_idx
    ON booking_seats (showtime_id, seat_number)
    WHERE is_active;

-- 10. Отзывы: одна оценка 1–5 от пользователя на фильм
CREATE TABLE reviews (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    movie_id BIGINT NOT NULL REFERENCES movies(id) ON DELETE CASCADE,
    rating SMALLINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment TEXT NOT NULL DEFAULT '',
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (user_id, movie_id)
);
