# Action Execution

YouPanel does not provide a web terminal.

Lifecycle:

```text
Browser submits action key and structured options
Laravel Form Request rejects raw commands
Policy and role checks run
Action catalog is loaded from config
Component working directory is resolved inside an approved root
Execution row is created
Queue job runs adapter
Output is redacted and stored under private storage
Audit and notifications are written
```

Local and test mode use `YOUPANEL_ACTION_DRIVER=mock`. Real process mode uses Symfony Process with fixed server-side command arrays.

High-risk actions require typed website name and password confirmation.
