# EventBite — Documented Visual Asset Inventory

All imagery used across EventBite is stored locally in `public/images/` and `backend/public/images/`. Zero external Dribbble URLs or unverified third-party hotlinks are referenced anywhere in the codebase.

## 1. Aspect Ratio & Focal Point Standards

| Image Category | CSS Class | Aspect Ratio | Object Fit | Default Focal Point |
| :--- | :--- | :---: | :---: | :---: |
| **Event Hero Banner** | `.eb-aspect-event-hero` | `21:9` (Desktop) / `16:10` (Mobile) | `cover` | `center 40%` |
| **Event Discovery Card** | `.eb-aspect-event-card` | `16:10` | `cover` | `center center` |
| **Food Menu Item Card** | `.eb-aspect-food-card` | `4:3` | `cover` | `center center` |
| **Event Flyer Poster** | `.eb-aspect-flyer` | `3:4` | `cover` | `center top` |
| **Order Thumbnail / Avatar** | `.eb-aspect-square` | `1:1` | `cover` | `center center` |

## 2. Verified First-Party Image Catalog

| Asset ID | Local Path | Category | Aspect Ratio | Focal Point | Fallback Usage |
| :--- | :--- | :--- | :---: | :---: | :--- |
| `island_music_reggae_concert` | `/images/island_music_reggae_concert.jpg` | Concerts | `16:10` | `center 40%` | Concerts, live stage & sound system events |
| `escovitch_fish` | `/images/escovitch_fish.jpg` | Fish Fries / Seafood | `4:3` | `center center` | Fish fries & default seafood menu items |
| `island_festival_sunset` | `/images/island_festival_sunset.jpg` | Food Festivals | `16:10` | `center 45%` | Food festivals & default event hero fallback |
| `island_culture_carnival` | `/images/island_culture_carnival.jpg` | Parties & Culture | `16:10` | `center 35%` | Parties, carnivals & cultural celebrations |
| `island_sports_championship` | `/images/island_sports_championship.jpg` | Community Events | `16:10` | `center center` | Community tournaments, regattas & family days |
| `jerk_chicken` | `/images/jerk_chicken.jpg` | Food Menu Item | `4:3` | `center center` | Jerk chicken platters & smoked meats |
| `fried_chicken` | `/images/fried_chicken.jpg` | Food Menu Item | `4:3` | `center center` | Fried chicken meals & combos |
| `crispy_island_wings` | `/images/crispy_island_wings.jpg` | Food Menu Item | `4:3` | `center center` | Chicken wings & small plates |
| `caribbean_curry_goat` | `/images/caribbean_curry_goat.jpg` | Food Menu Item | `4:3` | `center center` | Curry goat & stewed mains |
| `oxtail` | `/images/oxtail.jpg` | Food Menu Item | `4:3` | `center center` | Braised oxtail & premium platters |
| `bammy` | `/images/bammy.jpg` | Food Menu Item | `4:3` | `center center` | Cassava bammy, festival & bread sides |
| `fried_plantains_sides` | `/images/fried_plantains_sides.jpg` | Food Menu Item | `4:3` | `center center` | Plantains, rice & peas, and sides |
| `rum_punch` | `/images/rum_punch.jpg` | Food Menu Item | `4:3` | `center 40%` | Island punches, sorrel & beverages |
| `tropical_island_smoothie` | `/images/tropical_island_smoothie.jpg` | Food Menu Item | `4:3` | `center 40%` | Natural juices & tropical smoothies |

