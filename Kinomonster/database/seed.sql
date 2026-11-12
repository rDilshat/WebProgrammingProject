-- KinoMonster — тестовые данные
--
-- Запуск после schema.sql (из папки Kinomonster):
--   psql -h 127.0.0.1 -U kinomonster_app -d kinomonster -f database/seed.sql
--
-- Сеансы и брони считаются от сегодняшней даты, поэтому данные всегда «свежие»:
-- сеансы за последние 30 дней (для отчётов) и на 14 дней вперёд (для бронирования).
--
-- Тестовые аккаунты:
--   admin@kinomonster.kz  / Admin123!   (администратор)
--   user@kinomonster.kz   / User1234!   (пользователь)
--   остальные пользователи / password123

BEGIN;

-- Жанры
INSERT INTO genres (name) VALUES
    ('Drama'),
    ('Thriller'),
    ('Crime'),
    ('Science Fiction'),
    ('Fantasy'),
    ('Action'),
    ('Comedy'),
    ('History'),
    ('Mystery'),
    ('Talk Show'),
    ('Animation'),
    ('Romance'),
    ('Biography'),
    ('Adventure');

-- Фильмы (27 шт., трейлеры — YouTube)
INSERT INTO movies (slug, title, description, duration_min, release_year, poster, trailer_url) VALUES
    ('fight-club', 'Fight Club', 'An insomniac and a mysterious soap maker create an underground fight club that changes their lives.', 139, 1999, 'fight-club_poster.jpg', 'https://www.youtube.com/watch?v=ZWc-cLF_OyA'),
    ('joker', 'Joker', 'A failed comedian begins a slow descent into a dangerous identity of his own.', 122, 2019, 'joker_poster.jpg', 'https://www.youtube.com/watch?v=zm6GJwA10Lk'),
    ('no-country-for-old-men', 'No Country For Old Men', 'A hunter finds a fortune in drug money and is pursued by a relentless killer.', 122, 2007, 'no-country-for-old-men.jpg', 'https://www.youtube.com/watch?v=38A__WT3-o0'),
    ('blade-runner', 'Blade Runner', 'A blade runner must track down escaped replicants in a dark future Los Angeles.', 117, 1982, 'bladerunner.jpg', 'https://www.youtube.com/watch?v=eogpIG53Cis'),
    ('stranger-things', 'Stranger Things', 'A group of friends uncover extraordinary mysteries in their small town.', 50, 2016, 'stranger-things_poster.jpg', 'https://www.youtube.com/watch?v=mnd7sFt5c3A'),
    ('free-guy', 'Free Guy', 'A bank teller discovers he is actually a background character in an open-world video game.', 115, 2021, 'free-guy_poster.jpg', 'https://www.youtube.com/watch?v=nzJYriTUYVs'),
    ('chernobyl', 'Chernobyl', 'A historical drama about the people who faced the consequences of a nuclear disaster.', 65, 2019, 'chernobyl_poster.jpg', 'https://www.youtube.com/watch?v=MxTdHoI9S40'),
    ('death-on-the-nile', 'Death on the Nile', 'A glamorous river cruise becomes the scene of a mystery that only Poirot can solve.', 127, 2022, 'death-on-the-nile_poster.jpg', 'https://www.youtube.com/watch?v=_GEWd5ycn2k'),
    ('live-with-kelly', 'Live with Kelly and Ryan', 'A daytime talk show featuring interviews, entertainment and current events.', 42, 2017, 'live-with-kelly_poster.jpg', NULL),
    ('big-bang-theory', 'The Big Bang Theory', 'A comedy about brilliant scientists, friendship and everyday life.', 22, 2007, 'bbt_poster.jpg', 'https://www.youtube.com/watch?v=PAm8PV47jMM'),
    ('south-park', 'South Park', 'Four friends navigate absurd adventures in the town of South Park.', 22, 1997, 's-park_poster.jpg', 'https://www.youtube.com/watch?v=sv0RgGSKg0w'),
    ('the-stand', 'The Stand', 'Survivors of a devastating pandemic face a final conflict between good and evil.', 60, 2020, 'stands_poster.jpg', 'https://www.youtube.com/watch?v=RUEKAljs6d0'),
    ('friends', 'Friends', 'Six friends share their lives, careers and unforgettable moments in New York.', 22, 1994, 'friends_poster.jpg', 'https://www.youtube.com/watch?v=8wThS5WCzs4'),
    ('the-mandalorian', 'The Mandalorian', 'A lone bounty hunter travels through the outer reaches of the galaxy.', 40, 2019, 'mand_poster.jpg', 'https://www.youtube.com/watch?v=dGU5CPjAVZI'),
    ('space-sweepers', 'Space Sweepers', 'A space crew discovers a dangerous secret while collecting orbital debris.', 136, 2021, 'space-sweepers_poster.jpg', 'https://www.youtube.com/watch?v=N_eaN8vq4Zk'),
    ('shimmer', 'Shimmer', 'A new story of discovery, ambition and unexpected connections.', 48, NULL, 'shimmer_poster.jpg', NULL),
    ('bridgerton', 'Bridgerton', 'A period drama about family, society and romance in Regency-era London.', 60, 2020, 'bridgerton_poster.jpg', 'https://www.youtube.com/watch?v=N2UzaYgy_mw'),
    ('the-queens-gambit', 'The Queen''s Gambit', 'A gifted young chess player seeks mastery while overcoming personal challenges.', 60, 2020, 'queens-gambit_poster.jpg', 'https://www.youtube.com/watch?v=if4zFR5XIPM'),
    ('dark', 'Dark', 'A missing child reveals a mystery that spans generations and time itself.', 55, 2017, 'dark_poster.jpg', 'https://www.youtube.com/watch?v=rrwycJ08PSA'),
    ('lucifer', 'Lucifer', 'The Devil abandons Hell to run a nightclub and solve crimes in Los Angeles.', 45, 2016, 'lucifer_poster.jpg', 'https://www.youtube.com/watch?v=21JvDn4ritA'),
    ('shameless', 'Shameless', 'The Gallagher family finds its own way through life, work and family chaos.', 50, 2011, 'shameless_poster.jpg', 'https://www.youtube.com/watch?v=Dxz7XXpMFWA'),
    ('the-undoing', 'The Undoing', 'A therapist sees her carefully built life unravel after a violent death.', 50, 2020, 'the-undoing_poster.jpg', 'https://www.youtube.com/watch?v=if-VE8A4aK0'),
    ('the-trial-of-chicago-7', 'The Trial of the Chicago 7', 'A courtroom drama based on the trial of activists charged with conspiracy.', 129, 2020, 'the-trial-of-chicago_poster.jpg', 'https://www.youtube.com/watch?v=pUWCwDVn6gs'),
    ('wolf-of-wall-street', 'The Wolf of Wall Street', 'A stockbroker rises to wealth and excess before facing the consequences of his choices.', 180, 2013, 'the-wolf-of-wall-street_poster.jpg', 'https://www.youtube.com/watch?v=iszwuX1AK6A'),
    ('wonder-woman-1984', 'Wonder Woman 1984', 'Wonder Woman faces a powerful enemy and a dangerous wish in 1984.', 151, 2020, 'ww-84_poster.jpg', 'https://www.youtube.com/watch?v=psFf4KXJZoQ'),
    ('harry-potter', 'Harry Potter', 'A young wizard begins his education and discovers a magical world.', 152, 2001, 'harry-potter_poster.jpg', 'https://www.youtube.com/watch?v=VyHV0BRtdxo'),
    ('holidate', 'Holidate', 'Two strangers agree to be each other’s holiday plus-ones throughout the year.', 104, 2020, 'holidate_poster.jpg', 'https://www.youtube.com/watch?v=yHm-DJMZKYs');

-- Жанры фильмов
INSERT INTO movie_genres (movie_id, genre_id)
SELECT m.id, g.id
FROM (VALUES
    ('fight-club', 'Drama'),
    ('fight-club', 'Thriller'),
    ('joker', 'Crime'),
    ('joker', 'Drama'),
    ('no-country-for-old-men', 'Crime'),
    ('no-country-for-old-men', 'Drama'),
    ('blade-runner', 'Science Fiction'),
    ('blade-runner', 'Thriller'),
    ('stranger-things', 'Drama'),
    ('stranger-things', 'Fantasy'),
    ('free-guy', 'Action'),
    ('free-guy', 'Comedy'),
    ('chernobyl', 'Drama'),
    ('chernobyl', 'History'),
    ('death-on-the-nile', 'Mystery'),
    ('death-on-the-nile', 'Crime'),
    ('live-with-kelly', 'Talk Show'),
    ('big-bang-theory', 'Comedy'),
    ('south-park', 'Animation'),
    ('south-park', 'Comedy'),
    ('the-stand', 'Drama'),
    ('the-stand', 'Fantasy'),
    ('friends', 'Comedy'),
    ('the-mandalorian', 'Action'),
    ('the-mandalorian', 'Science Fiction'),
    ('space-sweepers', 'Action'),
    ('space-sweepers', 'Science Fiction'),
    ('shimmer', 'Drama'),
    ('bridgerton', 'Drama'),
    ('bridgerton', 'Romance'),
    ('the-queens-gambit', 'Drama'),
    ('dark', 'Drama'),
    ('dark', 'Mystery'),
    ('lucifer', 'Crime'),
    ('lucifer', 'Fantasy'),
    ('shameless', 'Comedy'),
    ('shameless', 'Drama'),
    ('the-undoing', 'Drama'),
    ('the-undoing', 'Mystery'),
    ('the-trial-of-chicago-7', 'Drama'),
    ('the-trial-of-chicago-7', 'History'),
    ('wolf-of-wall-street', 'Biography'),
    ('wolf-of-wall-street', 'Crime'),
    ('wolf-of-wall-street', 'Drama'),
    ('wonder-woman-1984', 'Action'),
    ('wonder-woman-1984', 'Adventure'),
    ('harry-potter', 'Adventure'),
    ('harry-potter', 'Fantasy'),
    ('holidate', 'Comedy'),
    ('holidate', 'Romance')
) AS v(slug, genre)
JOIN movies m ON m.slug = v.slug
JOIN genres g ON g.name = v.genre;

-- Залы (по 24 места — как схема мест на странице фильма)
INSERT INTO halls (name, capacity) VALUES
    ('Hall 1', 24),
    ('Hall 2', 24),
    ('VIP Hall', 24);

-- Сеансы: у каждого фильма свои 2 времени показа, каждый день
-- с 30 дней назад до 14 дней вперёд. Зал зависит от фильма, цена — от зала.
INSERT INTO showtimes (movie_id, hall_id, starts_at, price)
SELECT m.id,
       h.id,
       d::date + v.show_time::time,
       CASE h.name WHEN 'Hall 1' THEN 2000 WHEN 'Hall 2' THEN 2500 ELSE 3500 END
FROM (VALUES
    ('fight-club', '18:30'),
    ('fight-club', '21:15'),
    ('joker', '17:00'),
    ('joker', '20:00'),
    ('no-country-for-old-men', '16:45'),
    ('no-country-for-old-men', '19:30'),
    ('blade-runner', '18:00'),
    ('blade-runner', '21:00'),
    ('stranger-things', '15:00'),
    ('stranger-things', '18:15'),
    ('free-guy', '14:30'),
    ('free-guy', '20:30'),
    ('chernobyl', '16:00'),
    ('chernobyl', '19:00'),
    ('death-on-the-nile', '17:30'),
    ('death-on-the-nile', '22:00'),
    ('live-with-kelly', '12:00'),
    ('live-with-kelly', '15:00'),
    ('big-bang-theory', '13:00'),
    ('big-bang-theory', '16:00'),
    ('south-park', '14:00'),
    ('south-park', '17:00'),
    ('the-stand', '16:30'),
    ('the-stand', '20:30'),
    ('friends', '13:30'),
    ('friends', '18:30'),
    ('the-mandalorian', '15:30'),
    ('the-mandalorian', '19:30'),
    ('space-sweepers', '17:15'),
    ('space-sweepers', '21:30'),
    ('shimmer', '14:30'),
    ('shimmer', '18:00'),
    ('bridgerton', '15:00'),
    ('bridgerton', '20:00'),
    ('the-queens-gambit', '16:15'),
    ('the-queens-gambit', '21:00'),
    ('dark', '17:45'),
    ('dark', '22:00'),
    ('lucifer', '18:45'),
    ('lucifer', '21:45'),
    ('shameless', '19:15'),
    ('shameless', '22:15'),
    ('the-undoing', '17:00'),
    ('the-undoing', '20:45'),
    ('the-trial-of-chicago-7', '16:00'),
    ('the-trial-of-chicago-7', '20:15'),
    ('wolf-of-wall-street', '15:45'),
    ('wolf-of-wall-street', '21:15'),
    ('wonder-woman-1984', '14:15'),
    ('wonder-woman-1984', '19:45'),
    ('harry-potter', '13:15'),
    ('harry-potter', '18:15'),
    ('holidate', '16:45'),
    ('holidate', '20:30')
) AS v(slug, show_time)
JOIN movies m ON m.slug = v.slug
JOIN halls h ON h.id = (m.id % 3) + 1
CROSS JOIN generate_series(CURRENT_DATE - 30, CURRENT_DATE + 14, INTERVAL '1 day') AS d;

-- Пользователи (пароли захешированы password_hash)
INSERT INTO users (first_name, last_name, email, password_hash, role, created_at) VALUES
    ('Admin', 'KinoMonster', 'admin@kinomonster.kz', '$2y$10$pKvU9/kKX6/g2jymOTmVw.FgUFDkROi0PQzDI2rP9Akj8Vb3Z8iZa', 'admin', NOW() - INTERVAL '90 days'),
    ('Test', 'User', 'user@kinomonster.kz', '$2y$10$fiChNRnCvEZiat48ZcwutO/ha7xU/SMzNOonliBWp47zjmmlQ1G66', 'user', NOW() - INTERVAL '60 days'),
    ('Aruzhan', 'Seitkali', 'aruzhan@example.com', '$2y$10$yXgZdyorvPvZjE6xGayv8u1z2Vuj8ycbF99u5cGL88Wn52jxzQ9Fe', 'user', NOW() - INTERVAL '55 days'),
    ('Dias', 'Nurlanov', 'dias@example.com', '$2y$10$yXgZdyorvPvZjE6xGayv8u1z2Vuj8ycbF99u5cGL88Wn52jxzQ9Fe', 'user', NOW() - INTERVAL '50 days'),
    ('Madina', 'Akhmetova', 'madina@example.com', '$2y$10$yXgZdyorvPvZjE6xGayv8u1z2Vuj8ycbF99u5cGL88Wn52jxzQ9Fe', 'user', NOW() - INTERVAL '45 days'),
    ('Timur', 'Bekov', 'timur@example.com', '$2y$10$yXgZdyorvPvZjE6xGayv8u1z2Vuj8ycbF99u5cGL88Wn52jxzQ9Fe', 'user', NOW() - INTERVAL '40 days'),
    ('Aigerim', 'Sarsenova', 'aigerim@example.com', '$2y$10$yXgZdyorvPvZjE6xGayv8u1z2Vuj8ycbF99u5cGL88Wn52jxzQ9Fe', 'user', NOW() - INTERVAL '35 days'),
    ('Nursultan', 'Omarov', 'nursultan@example.com', '$2y$10$yXgZdyorvPvZjE6xGayv8u1z2Vuj8ycbF99u5cGL88Wn52jxzQ9Fe', 'user', NOW() - INTERVAL '28 days'),
    ('Dana', 'Kassymova', 'dana@example.com', '$2y$10$yXgZdyorvPvZjE6xGayv8u1z2Vuj8ycbF99u5cGL88Wn52jxzQ9Fe', 'user', NOW() - INTERVAL '21 days'),
    ('Alikhan', 'Zhaksylykov', 'alikhan@example.com', '$2y$10$yXgZdyorvPvZjE6xGayv8u1z2Vuj8ycbF99u5cGL88Wn52jxzQ9Fe', 'user', NOW() - INTERVAL '14 days'),
    ('Kamila', 'Yerlanova', 'kamila@example.com', '$2y$10$yXgZdyorvPvZjE6xGayv8u1z2Vuj8ycbF99u5cGL88Wn52jxzQ9Fe', 'user', NOW() - INTERVAL '7 days'),
    ('Erlan', 'Mukhtarov', 'erlan@example.com', '$2y$10$yXgZdyorvPvZjE6xGayv8u1z2Vuj8ycbF99u5cGL88Wn52jxzQ9Fe', 'user', NOW() - INTERVAL '3 days'),
    ('Zarina', 'Tulegenova', 'zarina@example.com', '$2y$10$yXgZdyorvPvZjE6xGayv8u1z2Vuj8ycbF99u5cGL88Wn52jxzQ9Fe', 'user', NOW() - INTERVAL '1 day');

-- Бронирования и места.
-- Примерно каждый 7-й сеанс получает 1–3 брони от разных пользователей,
-- в каждой брони 1–3 места. Часть броней отменена, часть будущих — в ожидании.
DO $$
DECLARE
    st RECORD;
    booking_count INTEGER;
    seat_count INTEGER;
    new_booking_id BIGINT;
    booking_user_id BIGINT;
    booking_status VARCHAR(20);
    user_ids BIGINT[];
BEGIN
    SELECT array_agg(id ORDER BY id) INTO user_ids FROM users WHERE role = 'user';

    FOR st IN
        SELECT id, starts_at, price FROM showtimes
        WHERE id % 7 = 0
          -- у нескольких фильмов нет будущих броней (для отчёта «фильмы без броней»)
          AND NOT (starts_at > NOW() AND movie_id % 9 = 0)
        ORDER BY id
    LOOP
        booking_count := 1 + (st.id % 3);

        FOR i IN 1..booking_count LOOP
            booking_user_id := user_ids[1 + ((st.id + i * 5) % array_length(user_ids, 1))];
            seat_count := 1 + ((st.id + i) % 3);

            booking_status := CASE
                WHEN (st.id + i) % 9 = 0 THEN 'cancelled'
                WHEN st.starts_at > NOW() AND (st.id + i) % 4 = 0 THEN 'pending'
                ELSE 'confirmed'
            END;

            INSERT INTO bookings (user_id, showtime_id, status, total_price, created_at)
            VALUES (
                booking_user_id,
                st.id,
                booking_status,
                st.price * seat_count,
                LEAST(st.starts_at - ((1 + st.id % 5) || ' days')::INTERVAL, NOW())
            )
            RETURNING id INTO new_booking_id;

            -- Места брони i: (i-1)*4+1 … — не пересекаются с другими бронями сеанса
            INSERT INTO booking_seats (booking_id, showtime_id, seat_number, is_active)
            SELECT new_booking_id, st.id, (i - 1) * 4 + s, booking_status <> 'cancelled'
            FROM generate_series(1, seat_count) AS s;
        END LOOP;
    END LOOP;
END $$;

-- Отзывы: часть пользователей оценила часть фильмов (оценки 2–5)
INSERT INTO reviews (user_id, movie_id, rating, comment, created_at)
SELECT u.id,
       m.id,
       2 + ((u.id * 3 + m.id) % 4),
       (ARRAY[
           'Great movie, would watch again!',
           'Solid story and acting.',
           'Not bad, but a bit too long.',
           'Amazing visuals and soundtrack.',
           'Good for a weekend evening.',
           'The ending surprised me.'
       ])[1 + ((u.id + m.id) % 6)],
       NOW() - ((u.id + m.id) % 30 || ' days')::INTERVAL
FROM users u
CROSS JOIN movies m
WHERE u.role = 'user' AND (u.id + m.id * 2) % 4 = 0;

COMMIT;
