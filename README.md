# module-new-day
[![Build Status](https://travis-ci.org/lotgd/module-new-day.svg?branch=master)](https://travis-ci.org/lotgd/module-new-day)

This module provides a "new day" event loop for daenerys.

It checks every time it hits a navigate-to event, if a new day is needed.
If yes, it saves the current scene, redirects to a new day and allows then
the restoration of the old event.

Please be aware that the official API might currently change at any point.

## Events
Currently, this module provides two events on its own:
- h/lotgd/module-new-day/before
- h/lotgd/module-new-day/after

### before

This event can be called if you want to redirect to a different scene before the actual new day is happening.
It is for example utilized to allow race selection.

Parameters:

- ```redirect, int|Scene``` \
Either 0 if no redirection is desired, or the scene to which it should redirect.

### after

This event should be used if you want things to happen at a new day. It can be used to revive
characters if they died.

Parameters:

- ```viewpoint, Viewpoint``` \
Either 0 if no redirection is desired, or the scene to which it should redirect.