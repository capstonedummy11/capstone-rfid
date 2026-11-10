# DuckDNS hostname for a Lightsail demonstration

Use this tutorial to point the demonstration hostname `rbasystemDemo.duckdns.org` at the Lightsail instance. If that hostname is already registered to your DuckDNS account, update its IP rather than creating it again. A hostname is needed before requesting an HTTPS certificate. DuckDNS supplies DNS only; the Laravel application, Nginx, and MySQL still run on the Lightsail server.

## 1. Keep the Lightsail address stable

In the [Lightsail console](https://lightsail.aws.amazon.com/), open **Instances > your instance > Networking**. Create and attach a **static IPv4 address** if one is not already attached. Copy the static IPv4 address. The DuckDNS hostname must point to this address, not the instance's private `172.x.x.x` address. Keep HTTP port **80** and HTTPS port **443** open in the Lightsail IPv4 firewall. [AWS static IP instructions](https://docs.aws.amazon.com/lightsail/latest/userguide/lightsail-create-static-ip.html).

## 2. Create the DuckDNS hostname

1. Open [DuckDNS](https://www.duckdns.org/) and sign in using one of the providers shown on its site.
2. In the **domains** area, enter `rbasystemDemo` and select **add domain**, unless it is already listed in your account. The resulting hostname is `rbasystemDemo.duckdns.org`. Choose another unique name if it is taken or if you registered a different name.
3. Find that domain's **current ip** field. Replace any automatically filled address with the Lightsail **static IPv4 address** from step 1, then select **update ip**. The automatically detected address may be the computer on which you opened DuckDNS, so verify the saved address before continuing.
4. Keep your DuckDNS account token private. It is not needed in `.env` for this one-time static-IP setup. If the static IP is later replaced, update the DuckDNS address again. A scheduled DuckDNS updater is unnecessary while the static IP remains attached.

DuckDNS also offers an [HTTPS update API](https://www.duckdns.org/spec.jsp) if you later automate address changes. This demonstration uses the dashboard to avoid placing its account token in shell history or project files.

## 3. Match the application hostname

On the Lightsail server, set the Nginx site's `server_name` to your full DuckDNS hostname. In `/var/www/capstone-rfid/.env`, set `APP_URL` to `https://` followed by the same hostname and set `SESSION_SECURE_COOKIE=true`. The HTTPS URL will work after the certificate step in the [server installation guide](SERVER_INSTALLATION_AND_FACE_LIVENESS.md#verify-dns-and-https-before-the-aws-face-setup).

## 4. Verify DNS and HTTP

Replace the example hostname in each command with your own DuckDNS hostname. On the server, check where it resolves:

```bash
getent ahostsv4 rbasystemDemo.duckdns.org
```

The IPv4 address in the output must match the Lightsail static IP. Check from your own computer too; DNS caches may update at different times. Once it matches, test the HTTP site:

```bash
curl -I http://rbasystemDemo.duckdns.org
```

If the address is wrong, recheck **current ip** on DuckDNS and wait for DNS to update. If the address is right but HTTP cannot connect, check the Lightsail port **80** rule, Nginx `server_name`, and Nginx service. A Laravel error response means HTTP reached the site but the application still needs troubleshooting.

Return to [Verify DNS and HTTPS](SERVER_INSTALLATION_AND_FACE_LIVENESS.md#verify-dns-and-https-before-the-aws-face-setup) to request the certificate and test HTTPS before demonstrating camera features.

Sources: [DuckDNS](https://www.duckdns.org/), [DuckDNS update specification](https://www.duckdns.org/spec.jsp), and [AWS Lightsail static IP instructions](https://docs.aws.amazon.com/lightsail/latest/userguide/lightsail-create-static-ip.html).
