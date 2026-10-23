# python

Mailer that finds website users without a profile image and emails them (issue #22). It connects to PostgreSQL as the read-only `mailer` role, which a Laravel migration ([web/laravel/database/migrations](../web/laravel/database/migrations)) allows to read only `id`, `name`, `email` and `profile_image` from `users`.
