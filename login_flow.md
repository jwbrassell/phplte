flowchart TD
    A[User visits login.php] --> B{Has Session?}
    B -->|Yes| C[Redirect to Dashboard]
    B -->|No| D[Show Login Form]
    
    subgraph Form Validation
    D --> E[Client-side Validation]
    E --> F[Submit Form]
    F --> G[verify_user.php]
    end

    subgraph LDAP Authentication
    G --> H[Sanitize Inputs]
    H --> I[Execute check_ldap.py]
    I --> J[Initialize Vault Utility]
    J --> K[Get LDAP Config from Vault]
    K --> L[LDAP Connection]
    L --> M{Valid Credentials?}
    M -->|No| N[Return Error]
    M -->|Yes| O[Search User in LDAP]
    O --> P{Check ADOM Groups}
    end

    subgraph Authorization
    P -->|Not Authorized| Q[Return Error]
    P -->|Authorized| R[Get User Details]
    R --> S[Return Success with User Info]
    end

    subgraph Session Management
    S --> T[Set Session Variables]
    T --> U[Log Success]
    end

    N --> V[Log Failed Attempt]
    Q --> V
    V --> W[Display Error]
    W --> D
    
    U --> X[Load Dashboard]
    X --> Y[header.php]
    X --> Z[footer.php]

    style A fill:#f9f,stroke:#333,stroke-width:2px
    style M fill:#fdd,stroke:#333,stroke-width:2px
    style P fill:#dfd,stroke:#333,stroke-width:2px
    style X fill:#bbf,stroke:#333,stroke-width:2px

    classDef vault fill:#ff9,stroke:#333,stroke-width:2px
    class J,K vault
