package apidocs

import (
	_ "embed"
	"encoding/json"
	"strings"
)

//go:embed md/overview.md
var Overview string

//go:embed docs.json
var docsJSON []byte

type Docs struct {
	Security struct {
		BearerAuth string `json:"bearer_auth"`
	} `json:"security"`
	Tags struct {
		BibleVerses     string `json:"bible_verses"`
		FeastDays       string `json:"feast_days"`
		Health          string `json:"health"`
		Patronages      string `json:"patronages"`
		ReligiousOrders string `json:"religious_orders"`
		Saints          string `json:"saints"`
	} `json:"tags"`
	Operations struct {
		GetHealth           string `json:"get_health"`
		GetSaintBySlug      string `json:"get_saint_by_slug"`
		ListBibleVerses     string `json:"list_bible_verses"`
		ListFeastDays       string `json:"list_feast_days"`
		ListPatronages      string `json:"list_patronages"`
		ListReligiousOrders string `json:"list_religious_orders"`
		ListSaints          string `json:"list_saints"`
	} `json:"operations"`
}

var Content = loadDocs()

func Markdown(content string) string {
	return strings.TrimSpace(content)
}

func Text(content string) string {
	return strings.TrimSpace(content)
}

func loadDocs() Docs {
	var docs Docs
	if err := json.Unmarshal(docsJSON, &docs); err != nil {
		panic("failed to parse API docs copy: " + err.Error())
	}
	return docs
}
