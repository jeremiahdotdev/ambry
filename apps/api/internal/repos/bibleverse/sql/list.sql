select bv.id, bv.book, bv.book_code, bv.book_order, bv.chapter, bv.verse, bv.greek_text, bv.english_text, bv.latin_text
from bible_verses bv
where (
    $1::text = ''
    or lower(bv.book) like $1
    or lower(coalesce(bv.greek_text, '')) like $1
    or lower(coalesce(bv.english_text, '')) like $1
    or lower(coalesce(bv.latin_text, '')) like $1
)
and ($2::text = '' or lower(bv.book) = $2)
and ($3::text = '' or lower(bv.book_code) = $3)
and ($4::int = 0 or bv.chapter = $4)
and ($5::int = 0 or bv.verse = $5)
order by bv.book_order asc, bv.chapter asc, bv.verse asc
limit $6 offset $7;
