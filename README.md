# "New Day" module
![Tests](https://github.com/lotgd/module-new-day/workflows/Tests/badge.svg)

This module provides a "new day" for Daenerys.

It checks every time it hits a navigate-to event, if a new day is needed.
If yes, it saves the current scene, redirects to a new day and allows then
the restoration of the old event. This currently means that the original viewpoint
technically still shows the old day, messages like "you died" would mean nothing.

Please be aware that the official API might currently change at any point.

## API
### Events
Currently, this module provides two events on its own:
- h/lotgd/module-new-day/before
- h/lotgd/module-new-day/after

#### before

This event can be called if you want to redirect to a different scene before the actual new day is happening.
It is for example utilized to allow race selection.

Parameters:

- ```redirect, int|Scene``` \
Either 0 if no redirection is desired, or the scene to which it should redirect.

#### after

This event should be used if you want things to happen at a new day. It can be used to revive
characters if they died.

Parameters:

- ```viewpoint, Viewpoint``` \
Either 0 if no redirection is desired, or the scene to which it should redirect.