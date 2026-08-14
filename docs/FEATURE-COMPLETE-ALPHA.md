# Feature-complete architecture definition

CGM Core 3.0.0-alpha.4.4 is considered **concept feature complete** when evaluated as the Core platform itself: every major concept defined for the new architecture has a concrete runtime contract and implementation path in Core.

This does **not** mean every external plugin is already updated to consume the new API. In particular, provider-owned data stays provider-owned. Game Linker must ship its companion bridge before Core may write Game Linker relationships. This is an integration/version rollout task, not a missing Core storage subsystem.

Likewise, Core only installs native builder UI hooks where a stable public extension surface is available and verified. It provides stable builder-neutral contracts for builders whose public third-party registration surface is incomplete or not documented, rather than coupling Core to private APIs.

Beta is for proving these contracts in the real CGM stack and polishing the experience.
