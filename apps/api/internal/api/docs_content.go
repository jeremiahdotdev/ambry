package api

import _ "embed"

//go:embed md/overview.md
var apiOverview string

//go:embed css/docs.css
var docsCustomCSS string
