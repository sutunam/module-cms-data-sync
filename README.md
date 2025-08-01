# CMS Data Sync - Magento 2 Extension
This extension will be extremely useful if it takes too much time of yours to
reproduce all cms block/ page from the testing site to your production site.
CMS within lots of configuration, lots of media, addition style, blocks ?
No matter how much it is, just only 1-2 click(s) and blinking, we will do the rest with the best.

# Feature:
- Pull All/ Pull each item(s) from the remote site to our site
- Push All/ Push each item(s) from your site, and update it to the remote site.
- Syncing media during sync data in 2 sites

# Configurations:
Go to Stores > Configuration > Sutunam > Cms Data Sync
![Go to Stores > Configuration > Sutunam > Cms Data Sync](https://i.imgur.com/SxoWoWH.png)

Descriptions of configurations:
- Enable: enabled/disabled this extension.
- Website name: remote host site name
- Website url: remote host site url(using to get url api endpoint)
- Username: admin username account of the remote site
- Password: admin password account of the remote site
- Sync media: enable to sync media during syncing
- Root dir and sub dir(depending on sync media config): mapping with remote directory where stores media of remtoe host
- Is Overwrite(should be disabled): the content will be overwritten via syncing

# Usage:

### Note:

- We have 3 positions that you can use to sync CMS data, namely ui listing primary button(Sync All), mass action and the button on the edit detail screen.
- The CMS block/page will be synced by Identifier + Store Views.
- Just click on it, the CMS data will be synced.

## Some common cases:
### 1. What it would be when the "Sync Media" configuration is on/off?

The media will be synced or not corresponding on this config. On is synced and Off is skipping sync media.

### 2. what it would be when the "Is Overwrite" configuration is Enabled/disabled?
- Enabled:
  All the content of CMS block/page will be overwritten by the content of remote host, within media as well if "Sync Media" config is On.
  In this case, we will meet 2 main cases. One is the case that block is not exist on the target site and the other is exist.
    - If it's exist, the content will be updated with the new content of remote host
    - If it's not exit, there's a block creating by corresponding CMS data of remote host.

- Disabled:
  All the CMS block/page that was exist on remote host will be skipped during syncing data.
  Any block which is not exist yet, It will be create the new one on the target host.


# Screenshot:

In listing page:
![](https://i.imgur.com/G3tuKMv.png)

In edit page:
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
