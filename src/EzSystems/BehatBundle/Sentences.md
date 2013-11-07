# BDD Sentences

For specific sentences check the tested bundle.

For the following documentationm you should remmeber:

* Words inside angular brackets (**[]**) are sentence definitions
* Words inside less and greater characters (**<>**) are user input


## Simple sentences


### Given

0. **@Given** ```/^I am logged in as ["'](.+)["'] with password ["'](.+)["']$/```
```
Given I am logged in as "<user>" with password "<password>"
```

0. **@Given** ```/^I am (?:at|on) (?:|the )(?:["']|)(.+)(?:["'] |)page$/```
```
Given I am on <which>page
Given I am at <which> page
Given I am on the "<which>" page
```

0. **@Given** ```/^I am on ["'](.+)["'] (?:page |) for ["'](.+)["'](?: Location|)$/```
```
Given I am on <page> for "<special-location>"
Given I am on <some> page for "<special>" Location
```
ex: ```Given I am on site map page for "Shopping" Location```

### When

To reduce the copy paste, for the When sentences that can also be used in the
Given sentences, they will also be posted here instead of the Given.
So verify that to use the sentences in the Given one's it's only need to change
the action (or all sentence) to the past (the When "click" will be Given "clicked")

0. **@Given** ```/^I clicked (?:on|at) (?:the |)["'](.+)["'] button$/```
0. **@When**  ``` /^I click (?:on|at) (?:the |)["'](.+)["'] button$/```
```
When I click on "<which>" button
When I click at the '<which>' button
```

0. **@Given** ```/^I clicked (?:on|at) (?:the |)["'](.+)["'] link$/```
0. **@When**  ```/^I click (?:on|at) (?:the |)["'](.+)["'] link$/```
```
When I click on "<which>" link
When I click at the '<which>' link
```

0. **@Given** ```/^on ([A-Za-z\s]*) I clicked at ["'](.+)["'] link$/```
0. **@When**  ```/^on ([A-Za-z\s]*) I click at ["'](.+)["'] link$/```
```
When on [some place] I click on "<which>" link
When on [some place] I click at the "<which>" link
```

0. **@When**  ```/^I go to (?:|the )["'](.+)["'](?:| page)$/```
```
When I go to '<page>"
When I go to the "<specific>" page
```

0. **@When**  ```/^I search for ["'](.+)["']$/```
```
When I search for "<text>"
```

0. **@When**  ```/^I go to (?:the |)["'](.+)["'] (?:page |)(?:for|the|at|on) ["'](.+)["'](?: location|)$/```
```
When I go to the "<some>" page on "<location>"
```

### Then

0. **@Then** ```/^I see (?:["']|)(.+)(?:["'] |)page$/```
```
Then I see <which>page
Then I see <which> page
Then I see "<which>" page
```

0. **@Then** ```/^I see search (\d+) result(?:s|)$/```
```
Then I see search <total> results
```

0. **@Then** ```/^I see ["'](.+)["'] button$/```
```
Then I see "<some>" button
```

0. **@Then** ```/^I see (?:a |)checkbox (?:field |)with ["'](.+)["'] label$/```
```
Then I see checkbox with "<some>" label
Then I see a checkbox field with '<some>' label
```

0. **@Then** ```/^I see (?:the |an |a |)["'](.+)["'] node$/```
```
Then I see a "<common>" node
Then I see the "<specific>" node
```

0. **@Then** ```/^on ([A-Za-z\s]*) I see (?:the |an |a |)["'](.+)["'] element$/```
```
Then on [some place] I see "<another>" element
Then on [some place] I see the "<specific>" element
```

0. **@Then** ```/^I see ["'](.+)["'] error(?: message|)$/```
```
Then I see "<message>" error
Then I see "<some>" errror message
```

0. **@Then** ```/^I see key ["'](.+)["'] with (?:value |)["'](.+)["']$/```
```
Then I see key "<key>" with "<value>"
Then I see key "<key>" with value "<value>"
```

0. **@Then** ```/^I see ["'](.+)["'] link$/
```
Then I see "<some>" link
```

0. **@Then** ```/^on ([A-Za-z\s]*) I see (?:the |an |a |)["'](.+)["'] link$/```
```
Then on [some place] I see a "<specific>" link
Then on [some place] I see the "<other>" link
```

0. **@Then** ```/^I see (?:a total of |)(\d+) ["'](.+)["'] elements listed$/```
```
Then I see <total> "<object>" elements listed
Then I see a total of <total> "<object>" elements listed
```

0. **@Then** ```/^I see (?:the |an |a |)["'](.+)["'] key$/```
```
Then I see "<some>" key
Then I see the '<real>' key
```

0. **@Then** ```/^I see (?:the |an |a |)["'](.+)["'] message$/```
```
Then I see a "<text>" message
Then I see the "<message with 'single quotation' mark>" message
Then I see the '<message with "quotation" marks>' message
```

0. **@Then** ```/^I see (?:the |)([A-Za-z\s]*) menu$/```
```
Then I see main menu
Then I see the side menu
```

0. **@Then** ```/^I see (?:the |an |a |)([A-Za-z\s]*) element$/```
```
Then I see <special> element
Then I see the <special> element
```

0. **@Then** ```/^I see ["'](.+)["'] text emphasized$/```
```
Then I see "<some>" text emphasized
```

0. **@Then** ```/^on ([A-Za-z\s]*) I see (?:the |)["'](.+)["'] text emphasized$/```
```
Then on [some place] I see "<some>" text emphasized
```

0. **@Then** ```/^I see (?:the |an |a |)["'](.+)["'] (?:title|topic)$/```
```
Then I see "<some>" title
Then I see the "<special>" topic
```

0. **@Then** ```/^I should be redirected to ["'](.+)["']$/```
```
Then I should be redirected to "<path>"
```

0. **@Then** ```/^I (?:don\'t|do not) see (?:a |the |)["'](.+)["'] link$/```
```
Then I don't see "<some>" link
Then I do not see a "<some>" link
```

0. **@Then** ```/^on ([A-Za-z\s]*) I (?:don\'t|do not) see (?:a |the |)["'](.+)["'] link$/```
```
Then on [some place] I don't see "<some>" link
Then on [some place] I do not see the "<some>" link
```

0. **@Then** ```/^I (?:don\'t|do not) see(?: the| ) ([A-Za-z\s]*) menu$/```
```
Then I don't see <which> menu
Then I do not see the <which> menu
```

0. **@Then** ```/^I (?:don\'t|do not) see(?: the| ) ([A-Za-z\s]*) element$/```
```
Then I don't see <which> element
Then I do not see the <which> element
```


## Tabled sentences

Notice that first row of the tables is for information/readability only, it will
be discard on the implementation part.


### Then tabled sentenced

0. **@Then** ```/^I see (?:the |)links:$/```
```
Then I see links:
    | Links  |
    | Link 1 |
    | Li...
```

0. **@Then** ```/^on ([A-Za-z\s]*) I see (?:the |)links:$/```
```
Then on [some place] I see the links:
    | Links  |
    | Link 1 |
    | Li...
```

0. **@Then** ```/^on ([A-Za-z\s]*) I see (?:the |)links in (?:the |)following order:$/```
```
Then on [some place] I see links in the following order:
    | Links  |
    | Link 1 |
    | Li...
```

 0. **@Then** ```/^I see links (?:for|of) Content objects(?:|\:)$/```
```
 Then I see links for Content objects:
    | Links  |
    | Link 1 |
    | Li...
```

0. **@Then** ```/^on ([A-Za-z\s]*) I see (?:the |)links (?:for|of) Content objects(?:\:|)$/```
```
Then on [some place] I see links of Content objects:
    | Links  |
    | Link 1 |
    | Li...
```

0. **@Then** ```/^I see links (?:for|of) Content objects in following order(?:\:|)$/```
```
Then I see links of Content objects in following order:
    | Links  |
    | Link 1 |
    | Li...
```

0. **@Then** ```/^I see (?:the |)links in(?:\:|)$/```
```
Then I see links
    | Links  | Type   |
    | Link 1 | Type A |
    | Link 2 | Ty...
```

0. **@Then** ```/^I see table with:$/```
```
Then I see table with:
    | Column 1 | Column N |
    | Data 1.1 | Data N.1 |
    | Data 1.2 | Data...
```

0. **@Then** ```/^I (?:don\'t|do not) see (?:the |)links(?:\:|)$/```
```
Then I don't see links:
    | Links  |
    | Link 1 |
    | Li...
```

0. **@Then** ```/^on ([A-Za-z\s]*) I (?:don\'t|do not) see (?:the |)links(?:\:|)$/```
```
Then on [some place] I do not see the links:
    | Links  |
    | Link 1 |
    | Li...
```

