-- Schema snapshot derived from the Laravel migrations at the monorepo root.
-- This file is for sqlc/local development only. It is not applied by the API.

create table saints (
    id uuid primary key,
    primary_name varchar not null,
    slug varchar not null unique,
    biography text,
    biography_sections json,
    biography_sources json,
    biography_format_model varchar,
    biography_formatted_at timestamp,
    biography_format_error text,
    profile_summary text,
    profile_subtitle varchar,
    profile_life_span json,
    profile_patronages json,
    profile_temperaments json,
    profile_key_struggles json,
    profile_key_virtues json,
    profile_church_roles json,
    profile_feast_days json,
    profile_related_saints json,
    profile_works json,
    profile_landmarks json,
    profile_sources json,
    profile_source_block json,
    profile_research_notes json,
    birth_year smallint,
    birth_year_qualifier varchar,
    death_year smallint,
    death_year_qualifier varchar,
    life_dates varchar,
    gender varchar,
    canonical_status varchar not null default 'saint',
    is_martyr boolean not null default false,
    is_doctor boolean not null default false,
    virtues json,
    vices json,
    roles json,
    ai_reason text,
    ai_confidence numeric(4,3),
    image_prompt text,
    image_cutout_url varchar,
    image_portrait_url varchar,
    image_thumb_url varchar,
    image_page_variant varchar,
    image_key_colors json,
    image_variant_reason text,
    image_variant_confidence numeric(4,3),
    created_at timestamp,
    updated_at timestamp
);

create table saint_aliases (
    id bigserial primary key,
    saint_id uuid not null references saints(id) on delete cascade,
    alias varchar not null,
    normalized_alias varchar not null,
    language varchar(12),
    citation_id uuid,
    confidence numeric(4,3) not null default 1,
    created_at timestamp,
    updated_at timestamp
);

create table feast_days (
    id bigserial primary key,
    saint_id uuid not null references saints(id) on delete cascade,
    month smallint not null,
    day smallint not null,
    calendar varchar not null default 'general',
    rite varchar,
    locality varchar,
    citation_id uuid,
    confidence numeric(4,3) not null default 1,
    created_at timestamp,
    updated_at timestamp
);

create table patronages (
    id uuid primary key,
    name varchar not null,
    slug varchar not null unique,
    category varchar,
    description text,
    created_at timestamp,
    updated_at timestamp
);

create table patronage_saint (
    id bigserial primary key,
    saint_id uuid not null references saints(id) on delete cascade,
    patronage_id uuid not null references patronages(id) on delete cascade,
    citation_id uuid,
    confidence numeric(4,3) not null default 1,
    is_tradition boolean not null default false,
    created_at timestamp,
    updated_at timestamp,
    unique (saint_id, patronage_id)
);

create table religious_orders (
    id uuid primary key,
    name varchar not null,
    slug varchar not null unique,
    abbreviation varchar,
    description text,
    created_at timestamp,
    updated_at timestamp
);

create table religious_order_saint (
    id bigserial primary key,
    saint_id uuid not null references saints(id) on delete cascade,
    religious_order_id uuid not null references religious_orders(id) on delete cascade,
    role varchar,
    citation_id uuid,
    confidence numeric(4,3) not null default 1,
    created_at timestamp,
    updated_at timestamp,
    unique (saint_id, religious_order_id, role)
);
