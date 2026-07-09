# Architecture

DancePro V2 uses a feature-based Laravel structure. New domain code should live under `app/Features`, while cross-cutting reusable code should live under `app/Shared`.

```text
app/
  Features/
    Auth/
    Users/
    Competition/
    Concert/
  Shared/
```

Each feature may contain actions, controllers, models, policies, requests, resources, services and support classes as needed. Laravel's default folders can still be used for framework-level code such as `App\Models\User` and service providers.

API controllers should stay small:

- Form Requests validate input.
- Resources shape output.
- Shared response helpers enforce the API envelope.
- Policies should hold authorization decisions as protected features are added.

## Related Documentation

- [API Guidelines](API-Guidelines.md)
- [Authentication](Authentication.md)
- [Security](Security.md)
- [Foundation Epic](../epics/Foundation.md)
