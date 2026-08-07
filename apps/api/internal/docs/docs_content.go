package docs

import (
	"os"
	"path/filepath"
	"runtime"
	"strings"
)

var CustomCSS = readSiblingFile("css", "docs.css")

var OverviewMarkdown = readSiblingFile("md", "overview.md")

func readSiblingFile(parts ...string) string {
	_, file, _, ok := runtime.Caller(0)
	if !ok {
		return ""
	}
	pathParts := append([]string{filepath.Dir(file), ".."}, parts...)
	content, err := os.ReadFile(filepath.Clean(filepath.Join(pathParts...)))
	if err != nil {
		return ""
	}
	return strings.TrimSpace(string(content))
}
