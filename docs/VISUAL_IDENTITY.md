# Visual identity and an inclusive marketplace

EventBite is a U.S. event marketplace for every culture and community. No single culture or cuisine is the default identity. The design direction is unchanged: a dark canvas, clean cards, strong type, the lime accent, restrained motion and responsive layouts (tokens in `public/css/01-tokens.css`).

## Hosts choose their event's traditions

- **The list:** `config/traditions.php` lists twelve traditions, alphabetically:
  - African;
  - African American;
  - Brazilian & Portuguese;
  - Caribbean;
  - East & Southeast Asian;
  - European;
  - Indigenous & Native American;
  - Latin American;
  - Middle Eastern & North African;
  - Pacific Islander;
  - South Asian;
  - Southern U.S.

  Adding one is one entry.
- **The form:** the event form has a *Traditions and cuisines* group. Hosts choose up to four; unticking all of them is respected.
- **Storage:** the choice is saved in `events.traditions` (migration `000032`).
- **Suggestions:** an event whose host has never chosen gets a suggestion from its own title, description and menu (`Event::traditionKeys()`). The form marks it as a suggestion to check. A host's own choice always wins.
- **Where it shows:** the homepage's *Browse by tradition* chips, the food sections that pick one event per tradition, search, and the event page's tradition chips all use the same keys.
- **Sample catalog:** the 20 sample events have traditions set by hand. Caribbean is one of eight traditions represented, not the default.

## Imagery

- **No flyer:** an event without uploaded artwork gets a generated typographic flyer (`/events/{id}/flyer.svg`, `App\Support\GeneratedFlyer`): its own name, date, city and traditions on the dark canvas, with one accent colour. Before, every such event showed the same palm-beach photograph.
- **Drafts:** draft events get a neutral placeholder from that URL, so their names are never exposed.
- **Sample events:** they no longer use the three stock photos (a palm-beach festival, a Trinidad-flag carnival, and a concert carrying another festival's banner). None of those depicted the events, which are held in parks across the U.S.
- **Menu photos:** a photo stays only on the dish it shows. Twenty mismatches were removed, for example a smoothie photo on lemonade, hot cider and ginger beer, a jerk-chicken photo on jerk pork, and a plantain photo on festival (a fried dumpling). Dishes without a photo show none.
- **Event page:** a generated flyer is shown whole on the event page rather than cropped.

## Wording

- **Homepage:**
  - The *Reggae & dancehall* section is now *Live music & nightlife* (concerts, DJs and dances across genres).
  - The search example is "tamales, jollof, Atlanta, family day".
  - A six-step strip says what EventBite does: discover, find events near you, buy tickets, order food, pay your way (the methods hosts actually accept), get your ticket.
- **Events page:** the music shortcut reads "Concerts, DJs & live sets".
- **Sign-up:** offers U.S. dollars only. The Jamaican dollar stays supported for historical orders.
- **Spelling:** customer pages use U.S. spelling ("organizer").
- **Checkout:** says texts carry "order and payment updates", not "your ticket", because a ticket is only issued once payment is confirmed.
- **Fallback initial:** an organizer without a name shows "E", not "I" for "Island".

## Mobile purchase

The full flow (event → tickets → food → checkout → ticket) was completed at 375px. Pages were checked at 430, 390 and 360px with no sideways scrolling.

- On phones the event page keeps a bar above the bottom navigation with the starting price and *Get tickets*, or *Sign in to buy*. It shows only when something can be bought.
- Quantity steppers are 44px touch targets on phones.
- The "Back to event" link has a larger tap area.
- Category and availability badges on cards no longer overlap.
