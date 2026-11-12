-- KinoMonster — сложные запросы для отчётов (End-term, раздел 4)
-- Все запросы работают на schema.sql + seed.sql и выводятся в админке (Reports).
-- Проверка: psql -h 127.0.0.1 -U kinomonster_app -d kinomonster -f database/queries.sql

-- 1. Выручка и число броней по каждому фильму
--    JOIN 3 таблиц + COUNT + SUM + GROUP BY + сортировка
SELECT m.title,
       COUNT(b.id)        AS bookings,
       SUM(b.total_price) AS revenue
FROM bookings b
JOIN showtimes s ON s.id = b.showtime_id
JOIN movies m    ON m.id = s.movie_id
WHERE b.status <> 'cancelled'
GROUP BY m.id, m.title
ORDER BY revenue DESC, m.title;

-- 2. Фильмы со средней оценкой не ниже 4 и хотя бы 2 отзывами
--    JOIN + AVG + COUNT + GROUP BY + HAVING
SELECT m.title,
       ROUND(AVG(r.rating), 2) AS avg_rating,
       COUNT(r.id)             AS reviews
FROM movies m
JOIN reviews r ON r.movie_id = m.id
GROUP BY m.id, m.title
HAVING AVG(r.rating) >= 4 AND COUNT(r.id) >= 2
ORDER BY avg_rating DESC, reviews DESC;

-- 3. Фильмы без единой активной брони на ближайшие 7 дней
--    LEFT JOIN + IS NULL + фильтр по датам
SELECT m.title
FROM movies m
LEFT JOIN showtimes s
       ON s.movie_id = m.id
      AND s.starts_at BETWEEN NOW() AND NOW() + INTERVAL '7 days'
LEFT JOIN bookings b
       ON b.showtime_id = s.id
      AND b.status <> 'cancelled'
GROUP BY m.id, m.title
HAVING COUNT(b.id) = 0
ORDER BY m.title;

-- 4. Пользователи, потратившие больше среднего
--    подзапрос в HAVING + JOIN + SUM
SELECT u.first_name || ' ' || u.last_name AS user_name,
       u.email,
       SUM(b.total_price) AS spent
FROM users u
JOIN bookings b ON b.user_id = u.id
WHERE b.status <> 'cancelled'
GROUP BY u.id
HAVING SUM(b.total_price) > (
    SELECT AVG(user_total)
    FROM (
        SELECT SUM(total_price) AS user_total
        FROM bookings
        WHERE status <> 'cancelled'
        GROUP BY user_id
    ) AS totals
)
ORDER BY spent DESC;

-- 5. Брони и выручка по дням за последние 30 дней
--    запрос по датам + COUNT + SUM + GROUP BY
SELECT DATE(b.created_at)  AS day,
       COUNT(*)            AS bookings,
       SUM(b.total_price)  AS revenue
FROM bookings b
WHERE b.created_at >= CURRENT_DATE - INTERVAL '30 days'
  AND b.status <> 'cancelled'
GROUP BY DATE(b.created_at)
ORDER BY day;

-- 6. Самый популярный сеанс (максимум проданных мест) в каждом зале
--    MAX + коррелированный подзапрос + JOIN 4 таблиц
SELECT h.name AS hall,
       m.title,
       s.starts_at,
       COUNT(bs.id) AS seats_sold
FROM halls h
JOIN showtimes s      ON s.hall_id = h.id
JOIN movies m         ON m.id = s.movie_id
JOIN booking_seats bs ON bs.showtime_id = s.id AND bs.is_active
GROUP BY h.id, h.name, m.title, s.id, s.starts_at
HAVING COUNT(bs.id) = (
    SELECT MAX(seat_total)
    FROM (
        SELECT COUNT(*) AS seat_total
        FROM booking_seats bs2
        JOIN showtimes s2 ON s2.id = bs2.showtime_id
        WHERE bs2.is_active AND s2.hall_id = h.id
        GROUP BY bs2.showtime_id
    ) AS per_showtime
)
ORDER BY h.name, s.starts_at
LIMIT 10;

-- 7. Заполняемость залов за прошедшие 30 дней, %
--    LEFT JOIN + COUNT + SUM + вычисления
SELECT h.name AS hall,
       COUNT(DISTINCT s.id)                AS showtimes,
       COUNT(bs.id)                        AS seats_sold,
       ROUND(100.0 * COUNT(bs.id) / (COUNT(DISTINCT s.id) * h.capacity), 1) AS occupancy_percent
FROM halls h
JOIN showtimes s           ON s.hall_id = h.id
                          AND s.starts_at BETWEEN NOW() - INTERVAL '30 days' AND NOW()
LEFT JOIN booking_seats bs ON bs.showtime_id = s.id AND bs.is_active
GROUP BY h.id, h.name, h.capacity
ORDER BY occupancy_percent DESC;

-- 8. Топ-5 жанров по числу проданных мест
--    JOIN через связь многие-ко-многим + COUNT + GROUP BY + LIMIT
SELECT g.name AS genre,
       COUNT(bs.id) AS seats_sold
FROM genres g
JOIN movie_genres mg  ON mg.genre_id = g.id
JOIN showtimes s      ON s.movie_id = mg.movie_id
JOIN booking_seats bs ON bs.showtime_id = s.id AND bs.is_active
GROUP BY g.id, g.name
ORDER BY seats_sold DESC
LIMIT 5;

-- 9. Ближайшие сеансы с ценой до 3000 и свободными местами (фильтр по нескольким условиям)
--    JOIN + подзапрос + несколько условий + сортировка
SELECT m.title,
       h.name AS hall,
       s.starts_at,
       s.price,
       h.capacity - (
           SELECT COUNT(*) FROM booking_seats bs
           WHERE bs.showtime_id = s.id AND bs.is_active
       ) AS free_seats
FROM showtimes s
JOIN movies m ON m.id = s.movie_id
JOIN halls h  ON h.id = s.hall_id
WHERE s.starts_at BETWEEN NOW() AND NOW() + INTERVAL '2 days'
  AND s.price <= 3000
  AND m.is_active
ORDER BY s.starts_at, s.price
LIMIT 20;

-- 10. Новые пользователи по неделям (статистика по датам)
SELECT DATE_TRUNC('week', created_at)::date AS week,
       COUNT(*) AS new_users
FROM users
GROUP BY week
ORDER BY week;
