package saint

import (
	"encoding/json"
	"net/http"
	"testing"

	"github.com/danielgtaylor/huma/v2/humatest"
)

func TestSavedAvatarInSaintProfiles(t *testing.T) {
	avatarURL := "https://images.example.com/st-test/avatar.png"
	portraitURL := "https://images.example.com/st-test/portrait.webp"
	for _, avatar := range []*string{&avatarURL, nil} {
		for _, path := range []string{"/api/v1/saints/st-test", "/api/v1/saint-of-the-day?date=2026-10-13"} {
			detail := Detail{Slug: "st-test", ImagePortraitURL: &portraitURL, ImageAvatarURL: avatar}
			repo := &fakeRepo{detail: detail, selection: OfTheDay{Date: "2026-10-13", Saint: detail}}
			_, api := humatest.New(t)
			RegisterRoutes(api, NewService(repo))
			response := api.Get(path)
			if response.Code != http.StatusOK {
				t.Fatalf("expected 200 for %s, got %d", path, response.Code)
			}
			var body map[string]any
			if err := json.Unmarshal(response.Body.Bytes(), &body); err != nil {
				t.Fatal(err)
			}
			if profile, ok := body["saint"].(map[string]any); ok {
				body = profile
			}
			if body["image_portrait_url"] != portraitURL {
				t.Fatal("original portrait changed")
			}
			value, exists := body["image_avatar_url"]
			if avatar == nil && exists {
				t.Fatal("missing avatar must be omitted")
			}
			if avatar != nil && value != avatarURL {
				t.Fatalf("expected saved avatar, got %v", value)
			}
		}
	}
}
