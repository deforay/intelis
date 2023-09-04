

### For custom locales (the ones that do not exist on Ubuntu)
```bash
cd /usr/share/i18n/locales/

sudo cp fr_FR fr_CM
sudo echo "fr_CM.UTF-8 UTF-8" >> /etc/locale.gen

sudo cp en_US en_CM
sudo echo "en_CM.UTF-8 UTF-8" >> /etc/locale.gen

sudo locale-gen
```
