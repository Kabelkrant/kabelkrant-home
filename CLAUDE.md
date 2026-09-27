# Algemene werkwijze

## Git: altijd committen en pushen

Na elke afgeronde aanpassing aan een project, zonder dat ik erom hoef te vragen:

1. Commit de wijzigingen met een korte, duidelijke commit-message in het Nederlands.
2. Push direct naar de remote (`git push`), ook als je op `main` werkt. Maak geen aparte branch of pull request, tenzij ik daarom vraag.

Uitzonderingen en randgevallen:
- Is het project nog geen git-repository? Meld dat en vraag of ik er een wil (en of er een GitHub-repo bij moet).
- Is er geen remote ingesteld? Commit lokaal en meld dat pushen niet kon.
- Mislukt de push (bijv. remote loopt voor)? Niet forceren; meld het en stel een oplossing voor.
- Commit nooit geheimen (wachtwoorden, API-keys, `.env`-bestanden). Twijfel je, vraag het eerst.

## Iconen

Nieuwe of gewijzigde iconen in `public/icons/` (bijv. via het beheer geüpload) horen in de repository: neem ze altijd mee bij het committen, zonder het eerst te vragen. Commit ze bij voorkeur apart van codewijzigingen (bijv. "Iconen toegevoegd: x.svg, y.svg").
