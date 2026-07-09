# # Shared Hosting Site Toolkit
#
# Build-, Runtime- und Deploy-Gerüst für kleine PHP-Shared-Hosting-Seiten
# ([warum Shared Hosting und PHP](https://ysdani.com/blog/warum-php-shared-hosting)).
# Die Template-Ebene (Renderer, Schemas) trägt mehrsprachige Inhalte; die
# PHP-Runtime bringt wiederverwendbare Module wie Token-Verwaltung, Security
# (Rate-Limiting, CAPTCHA) und Task-Dispatching mit. Die aktuelle
# Karriere-Profil-Vorlage — **Startseite, Lebenslauf, Kontakt, Blog** — ist
# die erste konkrete Anwendung darauf.
#
# Zielbild: Vorlage-Repos importieren das Gerüst per Composer — für kleine,
# leicht dynamische Websites wie Karriere-Profile oder Kataloge kleiner
# Unternehmen ohne E-Commerce. Eine neue Site soll dann nur noch
# Konfiguration, Inhalt, Templates und CSS brauchen. Noch liegen Gerüst und
# Referenz-Vorlage in einem Repository; auf der Roadmap stehen die Extraktion
# als versioniertes Composer-Paket und optionale Module — eine Site lädt nur,
# was sie braucht, und der Vendor-Baum bleibt klein.
#
# Arbeitsteilung im Code: PHP besitzt die HTTP-Runtime und die öffentliche
# `cli`, Python die Build- und Deploy-Automatisierung; Bash läuft nur in
# CI-Containern.
#
# [ysdani.com](https://ysdani.com) nutzt diese Vorlage produktiv — ausgewählte
# Betriebs- und Architekturentscheidungen dahinter sind im
# [Blog](https://ysdani.com/blog) dokumentiert, jeweils mit Verweis auf den Code.
# [preview.ysdani.com](https://preview.ysdani.com) ist der Preview-Deploy dazu.
#
# **Was kann es?**
#
# <!-- TOC -->
