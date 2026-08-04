package saint

import "github.com/jeremiahdotdev/ambry/apps/api/internal/database"

type FeastDay struct {
	Month int    `json:"month" doc:"Feast month, 1 through 12." example:"3"`
	Day   int    `json:"day" doc:"Feast day of month." example:"17"`
	Name  string `json:"name" doc:"Calendar or feast-day label." example:"general"`
}

type SearchResult struct {
	ID              string     `json:"id" doc:"Saint UUID." example:"e7bc3ba9-cb92-5d62-bfcd-e321ab09254b"`
	PrimaryName     string     `json:"primary_name" doc:"Primary display name." example:"St. Patrick"`
	Slug            string     `json:"slug" doc:"Stable URL slug." example:"st-patrick"`
	CanonicalStatus string     `json:"canonical_status" doc:"Canonical status." example:"saint"`
	BirthYear       *int       `json:"birth_year,omitempty" doc:"Birth year when known." example:"385"`
	DeathYear       *int       `json:"death_year,omitempty" doc:"Death year when known." example:"461"`
	Summary         *string    `json:"summary,omitempty" doc:"Short profile summary or biography excerpt."`
	FeastDays       []FeastDay `json:"feast_days" doc:"Associated feast days."`
	Patronages      []string   `json:"patronages" doc:"Associated patronages."`
	ReligiousOrders []string   `json:"religious_orders" doc:"Associated religious orders."`
}

type Detail struct {
	ID              string     `json:"id" doc:"Saint UUID."`
	PrimaryName     string     `json:"primary_name" doc:"Primary display name."`
	Slug            string     `json:"slug" doc:"Stable URL slug."`
	CanonicalStatus string     `json:"canonical_status" doc:"Canonical status."`
	Biography       *string    `json:"biography,omitempty" doc:"Full biography text."`
	BirthYear       *int       `json:"birth_year,omitempty" doc:"Birth year when known."`
	DeathYear       *int       `json:"death_year,omitempty" doc:"Death year when known."`
	Aliases         []string   `json:"aliases" doc:"Known aliases."`
	FeastDays       []FeastDay `json:"feast_days" doc:"Associated feast days."`
	Patronages      []string   `json:"patronages" doc:"Associated patronages."`
	ReligiousOrders []string   `json:"religious_orders" doc:"Associated religious orders."`
	Locations       []Location `json:"locations" doc:"Profile landmark/location data when supplied in profile_landmarks JSON."`
	Titles          []string   `json:"titles" doc:"Role/title-like values from profile_church_roles or roles JSON."`
}

type Location struct {
	Name        string  `json:"name" doc:"Landmark name."`
	Location    *string `json:"location,omitempty" doc:"Location text."`
	Description *string `json:"description,omitempty" doc:"Landmark description."`
}

type SearchFilters struct {
	Query       string
	Type        string
	Patronage   string
	Order       string
	FeastMonth  int
	FeastDay    int
	Page        int
	PerPage     int
	Sort        string
	Direction   string
}

type SearchPage struct {
	Data       []SearchResult      `json:"data"`
	Pagination database.Pagination `json:"pagination"`
}
