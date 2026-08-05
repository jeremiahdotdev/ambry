package bibleverse

import "api/internal/database"

type BookName string

type BookCode string

type Verse struct {
	ID          string   `json:"id" doc:"Stable verse ID." example:"newadvent-bible-gen-001-001"`
	Book        BookName `json:"book" doc:"Bible book name." example:"Genesis" enum:"Genesis,Exodus,Leviticus,Numbers,Deuteronomy,Joshua,Judges,Ruth,1 Samuel,2 Samuel,1 Kings,2 Kings,1 Chronicles,2 Chronicles,Ezra,Nehemiah,Tobit,Judith,Esther,Job,Psalms,Proverbs,Ecclesiastes,Song of Songs,Wisdom,Sirach,Isaiah,Jeremiah,Lamentations,Baruch,Ezekiel,Daniel,Hosea,Joel,Amos,Obadiah,Jonah,Micah,Nahum,Habakkuk,Zephaniah,Haggai,Zechariah,Malachi,1 Maccabees,2 Maccabees,Matthew,Mark,Luke,John,Acts,Romans,1 Corinthians,2 Corinthians,Galatians,Ephesians,Philippians,Colossians,1 Thessalonians,2 Thessalonians,1 Timothy,2 Timothy,Titus,Philemon,Hebrews,James,1 Peter,2 Peter,1 John,2 John,3 John,Jude,Revelation"`
	BookCode    BookCode `json:"book_code" doc:"Short book code." example:"gen" enum:"gen,exo,lev,num,deu,jos,jdg,rut,1sa,2sa,1ki,2ki,1ch,2ch,ezr,neh,tob,jth,est,job,psa,pro,ecc,son,wis,sir,isa,jer,lam,bar,eze,dan,hos,joe,amo,oba,jon,mic,nah,hab,zep,hag,zec,mal,1ma,2ma,mat,mar,luk,joh,act,rom,1co,2co,gal,eph,phi,col,1th,2th,1ti,2ti,tit,phm,heb,jam,1pe,2pe,1jo,2jo,3jo,jud,rev"`
	BookOrder   int      `json:"book_order" doc:"Canonical book order." example:"1"`
	Chapter     int      `json:"chapter" doc:"Chapter number." example:"1"`
	Verse       int      `json:"verse" doc:"Verse number." example:"1"`
	GreekText   *string  `json:"greek_text,omitempty" doc:"Greek verse text when available."`
	EnglishText *string  `json:"english_text,omitempty" doc:"English verse text when available."`
	LatinText   *string  `json:"latin_text,omitempty" doc:"Latin verse text when available."`
}

type Filters struct {
	Query    string
	Book     string
	BookCode string
	Chapter  int
	Verse    int
	Page     int
	PerPage  int
}

type VersePage struct {
	Data       []Verse             `json:"data"`
	Pagination database.Pagination `json:"pagination"`
}
