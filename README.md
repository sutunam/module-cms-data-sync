# CMS Data Sync - Magento 2 Extension


- Description:
This extension streamlines the process of copying all CMS blocks and pages from your testing site to your production site. It works seamlessly with any number of configurations, media files, styles, or blocks, reducing the need for manual work.


- Goal:
Save time and effort by enabling users to migrate CMS content with just one or two clicks, ensuring accurate and efficient transfer between environments.

# Installation
## Composer

Add Sutunam Composer repository:

```json
"repositories": {
    "sutunam": {
        "type": "composer",
        "url": "https://composer.sutunam.com/m2/"
    }
}
```
Require the package:

```bash
composer require sutunam/module-cms-data-sync
```
Then, execute the following Magento commands:

```bash
bin/magento module:enable Sutunam_CmsDataSync
bin/magento setup:upgrade
bin/magento cache:flush
```

If you are in production mode, also run:

```bash
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f
```

# Configurations:

Go to Stores > Configuration > Sutunam > Cms Data Sync
![Go to Stores > Configuration > Sutunam > Cms Data Sync](https://i.imgur.com/SxoWoWH.png)

**Descriptions of configurations:**

- Enable – Turn the extension on or off.

- Website Name – Name of the remote site you want to copy content from.

- Website URL – The web address of the remote site, used to connect to its API.

- Username – Admin login name for the remote site.

- Password – Admin password for the remote site.

- Sync Media – Turn this on if you also want to copy images and other media files.

- Root Directory / Subdirectory – The folder location on the remote site where its media files are stored (used only if Sync Media is on).

- Overwrite Content (recommended: keep off) – If on, it will replace existing content on your site with the remote site’s content.

# Usage:

### Note:

You can sync CMS data using any of the three available options:

- Primary “Sync All” button in the UI listing.

- Mass Action option in the CMS listing view.

- Sync button on the CMS block/page edit screen.

The CMS block or page is matched and synced based on its Identifier and Store Views.
Simply click the desired sync option, and the CMS data will be transferred automatically.

## Some common cases:

1. “Sync Media” Setting
- On – Media files are synced along with CMS content.


- Off – Media files are skipped during syncing.

2. “Overwrite Content” Setting

**Enabled**

- All CMS blocks/pages are replaced with the remote host’s content.

- If Sync Media is also On, associated media files are replaced as well.

- Two scenarios:

- [ ] Block/Page exists → Content is updated with the remote host’s version.

- [ ] Block/Page does not exist → A new block/page is created using the remote host’s data.

**Disabled**

- Existing CMS blocks/pages on the target site are skipped during syncing.

- Any CMS block/page that does not exist on the target site is created using the remote host’s data.

# Screenshot:


1. In listing page:
![](https://i.imgur.com/G3tuKMv.png)


1. In each page:
![](https://i.imgur.com/dI29nze.png)

<i>Note: The cms page management is the same as the block ui listing page</i>

# Change log
    1.0.0
        Init extension
    1.0.1
        Fix bug missing data table layout_update,layout_link and widget_instance_page_layout when setting module
    1.0.2
        Compatible to m2.4.8
    1.0.3
        Fix phpcs
    1.0.4
        Encrypt password in database
    1.0.5
        Update README.md
