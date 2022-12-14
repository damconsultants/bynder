# bynder
Magento 2 Bynder Extension gives you a great opportunity to upload more “images”, “videos” and “documentation” to the Bynder Media Library next to the Magento admin.

1) How to Install extension using Manual Installation?

    1.1. Download the Bynder extension
  
    1.2. Unzip the file in a temporary directory/folder with name as Bynder.
   
    1.3. Put directory as per this folder structure: project_root/app/code/ DamConsultants / Bynder
  
    1.4. Run the following command in Magento 2 root folder
  
        1.4.1. php bin/magento setup:upgrade
    
        1.4.2. php bin/magento setup:di:compile
    
        1.4.3. php bin/magento setup:static-content:deploy
    
    
2) Using Composer
	
	If your php version is 8.0 or 8.1 then need composer versoin is 2.3 then you can install our extension. Using below command for install extension	
	
		composer require damconsultants/bynder:1.0.4
		
	How to Update Composer Version?
		
			Using Below Command to Update Composer Version
			
				composer self-update Or composer update

3) New Features

1) Set your Cron job Frequency and time where it run and which time it run.

    => 	Fetch Null SKU to Magento :- On this Cron job snyc. Only those Product SKU sync. whose is empty bynder attribute in magento side  like image or video or document and same product sku add on bynder meta property. Only those product sku sync. this is a cron job.

    =>	Remove sku from the bynder :- on this Cron job. Admin can remove some bynder image or video or document on product side and click update bynder asset button on image and videos section. When run this cron then remove product sku form bynder side.

    =>	Auto Add Data Product :- on this cron job , if you add a some product sku on the a selected meta property on the a image or video or Documents. So admin not need to add that image or video or Documents. When run this crone run it’s automatically add product sku wise.

    =>	Auto Replace Data Product :- On this cron Job, Replace your image or video or Documents. Admin user can change the selected meta property in bynder side. Example if you added one image in this product sku 24-UG01 but you can assign this product sku image to other sku and this sku image you can add new image.

    => Add compactview sku form bynder :-  on this cron job , when admin side can add Image or Video or Documents with Compact View that time this compact view select image or video or Documents assign product sku selected meta property.

2)  Remove the Client key and Secret key from the system.xml




                
              
