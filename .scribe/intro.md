# Introduction

REST API do obsługi wynajmu nieruchomości: użytkownicy, właściciele, najemcy, nieruchomości, pokoje i przypisania.

<aside>
    <strong>Base URL</strong>: <code>http://inz.test</code>
</aside>

    Ta dokumentacja opisuje wszystkie publiczne i autoryzowane endpointy aplikacji.

    - autentykacja: `POST /login` + nagłówek `Authorization: Bearer {token}`
    - role:
      - `admin` – pełny dostęp do wszystkich zasobów
      - `owner` – własne obiekty właściciela
      - `tenant` – dostęp do przypisanych najemców i ich przypisań

    Kod zapytań dostępny jest w kilku językach po prawej stronie strony, z domyślnymi przykładami `bash` i `javascript`.

