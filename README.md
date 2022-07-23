# bynder
Magento 2 Bynder Extension gives you a great opportunity to upload more “images”, “videos” and “documentation” to the Bynder Media Library next to the Magento admin.

1) How to Install extension using Manual Installation?

    1.1. Download the Bynder extension
  
    1.2. Unzip the file in a temporary directory/folder with name as Bynder.
   
    1.3. Put Admin IP Restriction directory as per this folder structure: project_root/app/code/ DamConsultants / Bynder
  
    1.4. Run the following command in Magento 2 root folder
  
        1.4.1. php bin/magento setup:upgrade
    
        1.4.2. php bin/magento setup:di:compile
    
        1.4.3. php bin/magento setup:static-content:deploy
    
    
2) Using Composer
    
	If your php version is 7.2 or 7.4 then need composer versoin is 2.2 then you can install our extension. Using below command for install extension

		composer require damconsultants/bynder:1.0.0
	
	If your php version is 8.0 or 8.1 then need composer versoin is 2.3 then you can install our extension. Using below command for install extension	
	
		composer require damconsultants/bynder:1.0.3
		
	How to Update Composer Version?
		
			Using Below Command to Update Composer Version
			
				composer self-update Or composer update
              
