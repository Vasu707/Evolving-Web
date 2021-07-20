# Vaultre API Code

**Introduction:**<br />
Vaultre is a property management platform in Australia that allows users to deal in buying and selling the properties. It also provides API endpoints to allow users to fetch the properties on their website.

**Requirement:**<br />
i. Fetch all the properties from Vaultre and save into the database.
ii. Match properties daily with cronjob and update the modified ones.


**Approach:**<br />
i. Insert all properties at the beginning, schedule a daily cronjob that saves the response of modified properties within last 24 hours as JSON into the database.

ii. Schedule another cron for every hour (depends on how frequently the data is changed everyday) which reads the JSON saved into the database, slice 30 records from it at once and proceed with the updation process. At the end, delete those 30 records from the JSON and update the field with rest of the values in JSON Format. 

**Methods:**<br />
i. Auth<br />
The authentication process of endpoints requires a token and secret key to be passed into the headers in the request.

ii. getAllSaleProperties<br />
Fetch all the properties and return array to be added into the databaseat the beginning

iii. updatePropertiesList<br />
Need to be called once a day (Cron)
Get the properties modified a day ago, store	 the response in JSON Format in options column

iv. updateModifiedProperties<br />
Need to be called hourly (Cron)
Create/update the modified properties reading the JSON of modified records

v. saveProperty<br />
Receive details of a property as array and action as second parameter that states either to create a new property or update the existing one. i.e 0 == create, 1 == update

vi. prepareMetaData<br />
Receive details of a property as array and return array of meta fields in required format, used inside save_Property Fn.

vii. curlSetup<br />
A private function that receives an endpoint slug as parameter and returns the response after sending the request to the endpoint. 