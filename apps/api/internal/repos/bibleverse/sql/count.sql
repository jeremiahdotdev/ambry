select count(*)
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
and ($5::int = 0 or bv.verse = $5);
